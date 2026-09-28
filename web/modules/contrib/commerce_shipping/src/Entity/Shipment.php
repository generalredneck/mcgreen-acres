<?php

declare(strict_types=1);

namespace Drupal\commerce_shipping\Entity;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface as PackageTypePluginInterface;
use Drupal\commerce_shipping\ProposedShipment;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity\BundleFieldDefinition;
use Drupal\physical\Weight;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;

/**
 * Defines the shipment entity class.
 *
 * @ContentEntityType(
 *   id = "commerce_shipment",
 *   label = @Translation("Shipment"),
 *   label_collection = @Translation("Shipments"),
 *   label_singular = @Translation("shipment"),
 *   label_plural = @Translation("shipments"),
 *   label_count = @PluralTranslation(
 *     singular = "@count shipment",
 *     plural = "@count shipments",
 *   ),
 *   bundle_label = @Translation("Shipment type"),
 *   handlers = {
 *     "event" = "Drupal\commerce_shipping\Event\ShipmentEvent",
 *     "list_builder" = "Drupal\commerce_shipping\ShipmentListBuilder",
 *     "storage" = "Drupal\commerce_shipping\ShipmentStorage",
 *     "access" = "Drupal\commerce_shipping\ShipmentAccessControlHandler",
 *     "permission_provider" = "Drupal\commerce_shipping\ShipmentPermissionProvider",
 *     "views_data" = "Drupal\commerce_shipping\ShipmentViewsData",
 *     "form" = {
 *       "default" = "Drupal\commerce_shipping\Form\ShipmentForm",
 *       "add" = "Drupal\commerce_shipping\Form\ShipmentForm",
 *       "add-modal" = "Drupal\commerce_shipping\Form\ShipmentAddModalForm",
 *       "edit" = "Drupal\commerce_shipping\Form\ShipmentForm",
 *       "edit-modal" = "Drupal\commerce_shipping\Form\ShipmentEditModalForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "resend-confirmation" = "Drupal\commerce_shipping\Form\ShipmentConfirmationResendForm",
 *     },
 *     "inline_form" = "Drupal\commerce_shipping\Form\ShipmentInlineForm",
 *     "route_provider" = {
 *       "default" = "Drupal\commerce_shipping\ShipmentRouteProvider",
 *     },
 *   },
 *   base_table = "commerce_shipment",
 *   admin_permission = "administer commerce_shipment",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "shipment_id",
 *     "bundle" = "type",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/admin/commerce/orders/{commerce_order}/shipments/{commerce_shipment}",
 *     "add-page" = "/admin/commerce/orders/{commerce_order}/shipments/add",
 *     "collection" = "/admin/commerce/orders/{commerce_order}/shipments",
 *     "add-form" = "/admin/commerce/orders/{commerce_order}/shipments/add/{commerce_shipment_type}",
 *     "add-modal-form" = "/admin/commerce/orders/{commerce_order}/shipments/add-modal/{commerce_shipment_type}",
 *     "edit-form" = "/admin/commerce/orders/{commerce_order}/shipments/{commerce_shipment}/edit",
 *     "edit-modal-form" = "/admin/commerce/orders/{commerce_order}/shipments/{commerce_shipment}/edit-modal",
 *     "delete-form" = "/admin/commerce/orders/{commerce_order}/shipments/{commerce_shipment}/delete",
 *     "resend-confirmation-form" = "/admin/commerce/orders/{commerce_order}/shipments/{commerce_shipment}/resend-confirmation",
 *     "state-transition-form" = "/admin/commerce/orders/{commerce_order}/shipments/{commerce_shipment}/{field_name}/{transition_id}"
 *   },
 *   bundle_entity_type = "commerce_shipment_type",
 *   field_ui_base_route = "entity.commerce_shipment_type.edit_form",
 * )
 */
class Shipment extends ContentEntityBase implements ShipmentInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    $uri_route_parameters['commerce_order'] = $this->getOrderId();
    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function clearRate(): static {
    $fields = ['amount', 'original_amount', 'shipping_method', 'shipping_service'];
    foreach ($fields as $field) {
      $this->set($field, NULL);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function populateFromProposedShipment(ProposedShipment $proposed_shipment): void {
    if ($proposed_shipment->getType() != $this->bundle()) {
      throw new \InvalidArgumentException(sprintf('The proposed shipment type "%s" does not match the shipment type "%s".', $proposed_shipment->getType(), $this->bundle()));
    }

    $this->set('order_id', $proposed_shipment->getOrderId());
    $this->set('title', $proposed_shipment->getTitle());
    $this->set('items', $proposed_shipment->getItems());
    $this->set('shipping_profile', $proposed_shipment->getShippingProfile());
    $this->set('package_type', $proposed_shipment->getPackageTypeId());
    foreach ($proposed_shipment->getCustomFields() as $field_name => $value) {
      if ($this->hasField($field_name)) {
        $this->set($field_name, $value);
      }
      else {
        $this->setData($field_name, $value);
      }
    }
    $this->prepareFields();
  }

  /**
   * {@inheritdoc}
   */
  public function getOrder(): ?OrderInterface {
    return $this->get('order_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrderId(): ?int {
    if (!$this->get('order_id')->isEmpty()) {
      return (int) $this->get('order_id')->target_id;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPackageType(): ?PackageTypePluginInterface {
    if (!$this->get('package_type')->isEmpty()) {
      $package_type_id = $this->get('package_type')->value;
      $package_type_manager = \Drupal::service('plugin.manager.commerce_package_type');
      return $package_type_manager->createInstance($package_type_id);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setPackageType(PackageTypePluginInterface $package_type): static {
    $this->set('package_type', $package_type->getId());
    $this->recalculateWeight();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingMethod(): ?ShippingMethodInterface {
    if (!$this->get('shipping_method')->isEmpty()) {
      return $this->get('shipping_method')->entity;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setShippingMethod(ShippingMethodInterface $shipping_method): static {
    $this->set('shipping_method', $shipping_method);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingMethodId(): ?int {
    if (!$this->get('shipping_method')->isEmpty()) {
      return (int) $this->get('shipping_method')->target_id;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setShippingMethodId(int $shipping_method_id): static {
    $this->set('shipping_method', $shipping_method_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingService(): ?string {
    if (!$this->get('shipping_service')->isEmpty()) {
      return $this->get('shipping_service')->value;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setShippingService(string $shipping_service): static {
    $this->set('shipping_service', $shipping_service);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingServiceLabel(): ?string {
    if (!$this->get('service_label')->isEmpty()) {
      return $this->get('service_label')->value;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setShippingServiceLabel(string $service_label): static {
    $this->set('service_label', $service_label);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingProfile(): ?ProfileInterface {
    return $this->get('shipping_profile')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setShippingProfile(ProfileInterface $profile): static {
    $this->set('shipping_profile', $profile);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): ?string {
    if (!$this->get('title')->isEmpty()) {
      return $this->get('title')->value;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title): static {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getItems(): array {
    return $this->get('items')->getShipmentItems();
  }

  /**
   * {@inheritdoc}
   */
  public function setItems(array $shipment_items): static {
    $this->set('items', $shipment_items);
    $this->recalculateWeight();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalQuantity(): string {
    $total_quantity = '0';
    foreach ($this->getItems() as $item) {
      $total_quantity = Calculator::add($total_quantity, $item->getQuantity());
    }
    return $total_quantity;
  }

  /**
   * {@inheritdoc}
   */
  public function hasItems(): bool {
    return !$this->get('items')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function addItem(ShipmentItem $shipment_item): static {
    $this->get('items')->appendItem($shipment_item);
    $this->recalculateWeight();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem(ShipmentItem $shipment_item): static {
    $this->get('items')->removeShipmentItem($shipment_item);
    $this->recalculateWeight();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalDeclaredValue(): ?Price {
    $total_declared_value = NULL;
    foreach ($this->getItems() as $item) {
      $declared_value = $item->getDeclaredValue();
      $total_declared_value = $total_declared_value ? $total_declared_value->add($declared_value) : $declared_value;
    }
    return $total_declared_value;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): ?Weight {
    if (!$this->get('weight')->isEmpty()) {
      return $this->get('weight')->first()->toMeasurement();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight(Weight $weight): static {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalAmount(): ?Price {
    if (!$this->get('original_amount')->isEmpty()) {
      return $this->get('original_amount')->first()->toPrice();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalAmount(Price $original_amount): static {
    $this->set('original_amount', $original_amount);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAmount(): ?Price {
    if (!$this->get('amount')->isEmpty()) {
      return $this->get('amount')->first()->toPrice();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setAmount(Price $amount): static {
    $this->set('amount', $amount);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdjustedAmount(array $adjustment_types = []): ?Price {
    $amount = $this->getAmount();
    if (!$amount) {
      return NULL;
    }
    foreach ($this->getAdjustments($adjustment_types) as $adjustment) {
      if (!$adjustment->isIncluded()) {
        $amount = $amount->add($adjustment->getAmount());
      }
    }
    $rounder = \Drupal::service('commerce_price.rounder');
    $amount = $rounder->round($amount);

    return $amount;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdjustments(array $adjustment_types = []): array {
    /** @var \Drupal\commerce_order\Adjustment[] $adjustments */
    $adjustments = $this->get('adjustments')->getAdjustments();
    // Filter adjustments by type, if needed.
    if ($adjustment_types) {
      foreach ($adjustments as $index => $adjustment) {
        if (!in_array($adjustment->getType(), $adjustment_types)) {
          unset($adjustments[$index]);
        }
      }
      $adjustments = array_values($adjustments);
    }

    return $adjustments;
  }

  /**
   * {@inheritdoc}
   */
  public function setAdjustments(array $adjustments): static {
    $this->set('adjustments', $adjustments);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addAdjustment(Adjustment $adjustment): static {
    $this->get('adjustments')->appendItem($adjustment);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeAdjustment(Adjustment $adjustment): static {
    $this->get('adjustments')->removeAdjustment($adjustment);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clearAdjustments(): static {
    $locked_callback = function ($adjustment) {
      /** @var \Drupal\commerce_order\Adjustment $adjustment */
      return $adjustment->isLocked();
    };
    $adjustments = array_filter($this->getAdjustments(), $locked_callback);
    $this->setAdjustments($adjustments);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackingCode(): ?string {
    return $this->get('tracking_code')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTrackingCode(string $tracking_code): static {
    $this->set('tracking_code', $tracking_code);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getState(): StateItemInterface {
    return $this->get('state')->first();
  }

  /**
   * {@inheritdoc}
   */
  public function getData($key, mixed $default = NULL): mixed {
    $data = [];
    if (!$this->get('data')->isEmpty()) {
      $data = $this->get('data')->first()->getValue();
    }
    return $data[$key] ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setData($key, $value): static {
    $this->get('data')->__set($key, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unsetData($key): static {
    if (!$this->get('data')->isEmpty()) {
      $data = $this->get('data')->first()->getValue();
      unset($data[$key]);
      $this->set('data', $data);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): ?int {
    if (!$this->get('created')->isEmpty()) {
      return (int) $this->get('created')->value;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): static {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippedTime(): ?int {
    if (!$this->get('shipped')->isEmpty()) {
      return (int) $this->get('shipped')->value;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setShippedTime(int $timestamp): static {
    $this->set('shipped', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $this->prepareFields();
    foreach (['order_id'] as $field) {
      if ($this->get($field)->isEmpty()) {
        throw new EntityMalformedException(sprintf('Required shipment field "%s" is empty.', $field));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    $profiles_to_delete = [];
    // Delete the shipping profiles referenced by the shipments being deleted.
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($entities as $shipment) {
      $shipping_profile = $shipment->getShippingProfile();
      if ($shipping_profile && !$shipping_profile->getOwnerId()) {
        $profiles_to_delete[] = $shipping_profile;
      }
    }
    if ($profiles_to_delete) {
      /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
      $profile_storage = \Drupal::service('entity_type.manager')->getStorage('profile');
      $profile_storage->delete($profiles_to_delete);
    }
  }

  /**
   * Ensures that the package_type and weight fields are populated.
   */
  protected function prepareFields() {
    if (empty($this->getPackageType()) && !empty($this->getShippingMethodId())) {
      $shipping_method = $this->getShippingMethod();
      if ($shipping_method) {
        $default_package_type = $shipping_method->getPlugin()->getDefaultPackageType();
        $this->set('package_type', $default_package_type->getId());
      }
    }
    $this->recalculateWeight();
  }

  /**
   * Recalculates the shipment's weight.
   */
  protected function recalculateWeight(): void {
    if (!$this->hasItems()) {
      // Can't calculate the weight if the items are still unavailable.
      return;
    }

    /** @var \Drupal\physical\Weight $weight */
    $weight = NULL;
    foreach ($this->getItems() as $shipment_item) {
      $shipment_item_weight = $shipment_item->getWeight();
      $weight = $weight ? $weight->add($shipment_item_weight) : $shipment_item_weight;
    }
    if ($package_type = $this->getPackageType()) {
      $package_type_weight = $package_type->getWeight();
      $weight = $weight->add($package_type_weight);
    }

    $this->setWeight($weight);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // The order backreference.
    $fields['order_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Order'))
      ->setDescription(t('The parent order.'))
      ->setSetting('target_type', 'commerce_order')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['package_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Package type'))
      ->setDescription(t('The package type.'))
      ->setRequired(TRUE)
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['shipping_method'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Shipping method'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'commerce_shipping_method')
      ->setSetting('display_description', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'commerce_shipping_rate',
        'weight' => 49,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'commerce_shipping_method',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['shipping_service'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Shipping service'))
      ->setRequired(TRUE)
      ->setDescription(t('The shipping service.'))
      ->setDefaultValue('')
      ->setSetting('max_length', 255);

    $fields['service_label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Service Label'))
      ->setDescription(t('The Service Label.'))
      ->setSetting('max_length', 255);

    $fields['shipping_profile'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setLabel(t('Shipping information'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'profile')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', ['target_bundles' => ['customer' => 'customer']])
      ->setDisplayOptions('form', [
        'type' => 'commerce_shipping_profile',
        'weight' => -10,
        'settings' => [],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The shipment title.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -20,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['items'] = BaseFieldDefinition::create('commerce_shipment_item')
      ->setLabel(t('Items'))
      ->setRequired(TRUE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['weight'] = BaseFieldDefinition::create('physical_measurement')
      ->setLabel(t('Weight', [], ['context' => 'physical']))
      ->setRequired(TRUE)
      ->setSetting('measurement_type', 'weight')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['original_amount'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Original amount'))
      ->setDescription(t('The original amount.'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['amount'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Amount'))
      ->setDescription(t('The amount.'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['adjustments'] = BaseFieldDefinition::create('commerce_adjustment')
      ->setLabel(t('Adjustments'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['tracking_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Tracking code'))
      ->setDescription(t('The shipment tracking code.'))
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 50,
      ]);

    $fields['state'] = BaseFieldDefinition::create('state')
      ->setLabel(t('State'))
      ->setDescription(t('The shipment state.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'state_transition_form',
        'settings' => [
          'require_confirmation' => TRUE,
          'use_modal' => TRUE,
        ],
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setSetting('workflow_callback', ['\Drupal\commerce_shipping\Entity\Shipment', 'getWorkflowId']);

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setDescription(t('A serialized array of additional data.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the shipment was created.'))
      ->setRequired(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the shipment was last updated.'))
      ->setRequired(TRUE);

    $fields['shipped'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Shipped'))
      ->setDescription(t('The time when the shipment was shipped.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $shipment_type = ShipmentType::load($bundle);
    if (!$shipment_type) {
      throw new \RuntimeException(sprintf('Could not load the "%s" shipment type.', $bundle));
    }
    $fields = [];
    $fields['shipping_profile'] = clone $base_field_definitions['shipping_profile'];
    $fields['shipping_profile']->setSetting('handler_settings', [
      'target_bundles' => [
        $shipment_type->getProfileTypeId() => $shipment_type->getProfileTypeId(),
      ],
    ]);

    return $fields;
  }

  /**
   * Gets the workflow ID for the state field.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return string
   *   The workflow ID.
   */
  public static function getWorkflowId(ShipmentInterface $shipment): string {
    if (!empty($shipping_method = $shipment->get('shipping_method')->entity)) {
      if (!empty($plugin = $shipping_method->getPlugin())) {
        return $plugin->getWorkflowId();
      }
    }

    return 'shipment_default';
  }

  /**
   * Returns field definition for the 'shipments' field.
   *
   * @param string|null $order_type_id
   *   The order type ID.
   */
  public static function buildShipmentsFieldDefinition(?string $order_type_id = NULL): BundleFieldDefinition {
    return BundleFieldDefinition::create('entity_reference')
      ->setTargetEntityTypeId('commerce_order')
      ->setTargetBundle($order_type_id)
      ->setName('shipments')
      ->setLabel('Shipments')
      ->setCardinality(BundleFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'commerce_shipment')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'type' => 'commerce_shipping_information',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function invalidateTagsOnSave($update) {
    // An entity was created or updated: invalidate its list cache tags. (An
    // updated entity may start to appear in a listing because it now meets that
    // listing's filtering requirements. A newly created entity may start to
    // appear in listings because it did not exist before.)
    $tags = $this->getListCacheTagsToInvalidate();

    // Do not invalidate 4xx-response cache tag, because commerce shipment is
    // not affected by possibly stale 404 pages as checkout is not cached for
    // anonymous users.
    if ($update) {
      // An existing entity was updated, also invalidate its unique cache tag.
      $tags = Cache::mergeTags($tags, $this->getCacheTagsToInvalidate());
    }
    Cache::invalidateTags($tags);
  }

}
