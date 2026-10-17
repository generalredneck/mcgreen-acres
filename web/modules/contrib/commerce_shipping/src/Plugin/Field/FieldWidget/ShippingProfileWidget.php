<?php

namespace Drupal\commerce_shipping\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of 'commerce_shipping_profile'.
 */
#[FieldWidget(
  id: 'commerce_shipping_profile',
  label: new TranslatableMarkup('Shipping information'),
  field_types: ['entity_reference_revisions'],
)]
class ShippingProfileWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $items[$delta]->getEntity();
    $order = $shipment->getOrder();
    $store = $order->getStore();
    if (!$items[$delta]->isEmpty()) {
      $profile = $items[$delta]->entity;
    }
    else {
      $settings = $this->fieldDefinition->getSetting('handler_settings');
      $profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => reset($settings['target_bundles']),
        'uid' => 0,
      ]);
    }
    $available_countries = [];
    foreach ($store->get('shipping_countries') as $country_item) {
      $available_countries[] = $country_item->value;
    }
    $inline_form = $this->inlineFormManager->createInstance('customer_profile', [
      'profile_scope' => 'shipping',
      'available_countries' => $available_countries,
      'address_book_uid' => $order->getCustomerId(),
      'admin' => TRUE,
    ], $profile);

    $element['profile'] = [
      '#parents' => array_merge($element['#field_parents'], [$items->getName(), $delta, 'profile']),
      '#inline_form' => $inline_form,
    ];
    $element['profile'] = $inline_form->buildInlineForm($element['profile'], $form_state);
    // Workaround for massageFormValues() not getting $element.
    $element['array_parents'] = [
      '#type' => 'value',
      '#value' => [$items->getName(), 'widget', $delta],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $new_values = [];
    foreach ($values as $delta => $value) {
      $element = NestedArray::getValue($form, $value['array_parents']);
      /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
      $inline_form = $element['profile']['#inline_form'];
      $new_values[$delta]['entity'] = $inline_form->getEntity();
    }
    return $new_values;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return $entity_type == 'commerce_shipment' && $field_name == 'shipping_profile';
  }

}
