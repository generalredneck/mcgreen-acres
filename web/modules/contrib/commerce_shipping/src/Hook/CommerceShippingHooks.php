<?php

namespace Drupal\commerce_shipping\Hook;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\FieldAccessInterface;
use Drupal\commerce_shipping\OrderShipmentSummaryInterface;
use Drupal\commerce_shipping\ProfileFieldCopyInterface;
use Drupal\commerce_store\Entity\Store;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

class CommerceShippingHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommerceShippingHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce_shipping\ProfileFieldCopyInterface $profileFieldCopy
   *   The profile copy service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\commerce_shipping\FieldAccessInterface $fieldAccess
   *   The field access service.
   * @param \Drupal\commerce_shipping\OrderShipmentSummaryInterface $orderShipmentSummary
   *   The order shipment summary.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ProfileFieldCopyInterface $profileFieldCopy,
    protected ModuleHandlerInterface $moduleHandler,
    protected FieldAccessInterface $fieldAccess,
    protected OrderShipmentSummaryInterface $orderShipmentSummary,
  ) {}

  /**
   * Implements hook_commerce_entity_trait_info_alter().
   */
  #[Hook('commerce_entity_trait_info_alter')]
  public function commerceEntityTraitInfoAlter(array &$definitions): void {
    // Expose the purchasable entity traits for every purchasable entity type.
    $entity_types = $this->entityTypeManager->getDefinitions();
    $entity_types = array_filter($entity_types, function (EntityTypeInterface $entity_type) {
      return $entity_type->entityClassImplements(PurchasableEntityInterface::class);
    });
    $entity_type_ids = array_keys($entity_types);

    $definitions['purchasable_entity_dimensions']['entity_types'] = $entity_type_ids;
    $definitions['purchasable_entity_shippable']['entity_types'] = $entity_type_ids;
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    if ($entity_type->id() !== 'commerce_store') {
      return [];
    }
    $fields['shipping_countries'] = BaseFieldDefinition::create('list_string')
      ->setLabel($this->t('Supported shipping countries'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('allowed_values_function', [
        Store::class,
        'getAvailableCountries',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    if (empty($bundles['commerce_order'])) {
      return;
    }

    $order_type_ids = array_keys($bundles['commerce_order']);
    $order_types = $this->entityTypeManager->getStorage('commerce_order_type')
      ->loadMultiple($order_type_ids);
    $shipment_type_storage = $this->entityTypeManager->getStorage('commerce_shipment_type');
    foreach ($bundles['commerce_order'] as $bundle => $info) {
      if (!isset($order_types[$bundle])) {
        continue;
      }
      $order_type = $order_types[$bundle];
      $shipment_type_id = $order_type->getThirdPartySetting('commerce_shipping', 'shipment_type');
      if (!$shipment_type_id) {
        continue;
      }
      $shipment_type = $shipment_type_storage->load($shipment_type_id);
      if (!$shipment_type) {
        continue;
      }
      // Bundle info is loaded on most requests. Store the shipping profile
      // type ID inside, so that it can be retrieved from the checkout pane
      // without having to load two bundle entities (order/shipment type).
      $shipping_profile_type_id = $shipment_type->getProfileTypeId();
      if ($shipping_profile_type_id != 'customer') {
        // As a further optimization, the profile type ID is only stored
        // if it's different from the default ("customer").
        $bundles['commerce_order'][$bundle]['shipping_profile_type'] = $shipping_profile_type_id;
      }
    }
  }

  /**
   * Implements hook_entity_form_display_alter().
   */
  #[Hook('entity_form_display_alter')]
  public function entityFormDisplayAlter(EntityFormDisplayInterface $form_display, array $context): void {
    if ($context['entity_type'] != 'profile') {
      return;
    }

    // The "shipping" form mode doesn't have a form display yet.
    // Default to hiding the tax_number field, it is only needed for billing.
    if ($context['form_mode'] == 'shipping' && $context['form_mode'] != $form_display->getMode()) {
      $form_display->removeComponent('tax_number');
    }
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    /** @var \Drupal\Core\Field\BaseFieldDefinition $field_definition */
    $field_definition = $context['items']->getFieldDefinition();
    $field_name = $field_definition->getName();
    $entity_type = $field_definition->getTargetEntityTypeId();
    $widget_name = $context['widget']->getPluginId();
    if ($field_name == 'shipping_countries' && $entity_type == 'commerce_store' && $widget_name == 'options_select') {
      $element['#options']['_none'] = $this->t('- All countries -');
      $element['#size'] = 5;
    }
  }

  /**
   * Implements hook_commerce_inline_form_PLUGIN_ID_alter().
   */
  #[Hook('commerce_inline_form_customer_profile_alter')]
  public function commerceInlineFormCustomerProfileAlter(array &$inline_form, FormStateInterface $form_state, array &$complete_form): void {
    // Attach the "Billing same as shipping" element.
    if ($this->profileFieldCopy->supportsForm($inline_form, $form_state)) {
      $this->profileFieldCopy->alterForm($inline_form, $form_state);
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_commerce_order_type_form_alter')]
  public function formCommerceOrderTypeFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $form_state->getFormObject()->getEntity();
    $shipment_type_id = $order_type->getThirdPartySetting('commerce_shipping', 'shipment_type');
    $shipment_type_storage = $this->entityTypeManager->getStorage('commerce_shipment_type');
    $shipment_types = $shipment_type_storage->loadMultiple();
    $shipment_types = array_map(function ($shipment_type) {
      return $shipment_type->label();
    }, $shipment_types);
    $shipment_type_ids = array_keys($shipment_types);

    $form['commerce_shipping'] = [
      '#type' => 'container',
      '#weight' => 4,
      '#element_validate' => [[static::class, 'orderTypeFormValidate']],
    ];
    $form['commerce_shipping']['enable_shipping'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable shipping for this order type'),
      '#default_value' => !empty($shipment_type_id),
    ];
    $form['commerce_shipping']['shipment_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Shipment type'),
      '#options' => $shipment_types,
      '#default_value' => $shipment_type_id ?: reset($shipment_type_ids),
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="commerce_shipping[enable_shipping]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['actions']['submit']['#submit'][] = [static::class, 'orderTypeFormSubmit'];
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    // Only show the "Shipments" operation link for commerce_order entities.
    if ($entity->getEntityTypeId() !== 'commerce_order') {
      return [];
    }

    $operations = [];
    $url = Url::fromRoute('entity.commerce_shipment.collection', [
      'commerce_order' => $entity->id(),
    ]);
    if ($url->access()) {
      $operations['shipments'] = [
        'title' => $this->t('Shipments'),
        'url' => $url,
        'weight' => 60,
      ];
    }

    return $operations;
  }

  /**
   * Implements hook_entity_field_access().
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if (!$this->moduleHandler->moduleExists('jsonapi')) {
      return AccessResult::neutral();
    }
    return $this->fieldAccess->handle($operation, $field_definition, $account, $items);
  }

  /**
   * Implements hook_entity_bundle_field_info().
   */
  #[Hook('entity_bundle_field_info')]
  public function entityBundleFieldInfo(EntityTypeInterface $entity_type, string $bundle): array {
    if ($entity_type->id() !== 'commerce_order') {
      return [];
    }

    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $this->entityTypeManager
      ->getStorage('commerce_order_type')
      ->load($bundle);
    if (!$order_type) {
      return [];
    }

    if (!empty($order_type->getThirdPartySetting('commerce_shipping', 'shipment_type'))) {
      $field_definition = Shipment::buildShipmentsFieldDefinition($bundle);
      return [$field_definition->getName() => $field_definition];
    }

    return [];
  }

  /**
   * Validation handler for commerce_shipping_form_commerce_order_type_form_alter().
   */
  public static function orderTypeFormValidate(array $element, FormStateInterface $form_state): void {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $form_state->getFormObject()->getEntity();
    $previous_value = $order_type->getThirdPartySetting('commerce_shipping', 'shipment_type');
    $settings = $form_state->getValue(['commerce_shipping']);

    // Don't allow shipping to be disabled if there's data in the field.
    if ($previous_value && !$settings['enable_shipping']) {
      $field_definition = Shipment::buildShipmentsFieldDefinition($order_type->id());
      $storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
      $query = $storage->getQuery()
        ->condition($storage->getEntityType()->getKey('bundle'), $order_type->id())
        ->exists($field_definition->getName() . '.' . $field_definition->getMainPropertyName())
        ->accessCheck(FALSE);
      if (!empty($query->execute())) {
        $form_state->setError($element['enable_shipping'], t('Shipping cannot be disabled until all orders with shipment data are deleted.'));
      }
    }
  }

  /**
   * Submission handler for commerce_shipping_form_commerce_order_type_form_alter().
   */
  public static function orderTypeFormSubmit(array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $form_state->getFormObject()->getEntity();
    $settings = $form_state->getValue(['commerce_shipping']);
    $shipment_type_id = $settings['enable_shipping'] ? $settings['shipment_type'] : '';
    $order_type->setThirdPartySetting('commerce_shipping', 'shipment_type', $shipment_type_id);
    $order_type->save();
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   */
  #[Hook('form_commerce_order_form_alter')]
  public function formCommerceOrderFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $order = $form_object->getEntity();
    if ($order instanceof OrderInterface &&
      isset($form['advanced'])) {
      $summary = $this->orderShipmentSummary->build($order);
      if (!empty($summary)) {
        $form['shipping_information'] = [
          '#type' => 'details',
          '#title' => t('Shipping information'),
          '#group' => 'advanced',
          '#open' => TRUE,
          '#weight' => 92,
        ];
        $form['shipping_information']['summary'] = $summary;
      }
    }
  }

}
