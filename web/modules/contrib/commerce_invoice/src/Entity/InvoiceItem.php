<?php

namespace Drupal\commerce_invoice\Entity;

use Drupal\commerce\Entity\CommerceContentEntityBase;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the invoice item entity class.
 *
 * @ContentEntityType(
 *   id = "commerce_invoice_item",
 *   label = @Translation("Invoice item"),
 *   label_singular = @Translation("invoice item"),
 *   label_plural = @Translation("invoice items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count invoice item",
 *     plural = "@count invoice items",
 *   ),
 *   handlers = {
 *     "event" = "Drupal\commerce_invoice\Event\InvoiceItemEvent",
 *     "storage" = "Drupal\commerce\CommerceContentEntityStorage",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *     },
 *     "views_data" = "Drupal\commerce\CommerceEntityViewsData",
 *     "inline_form" = "Drupal\commerce_invoice\Form\InvoiceItemInlineForm",
 *   },
 *   translatable = TRUE,
 *   base_table = "commerce_invoice_item",
 *   data_table = "commerce_invoice_item_field_data",
 *   admin_permission = "administer commerce_invoice",
 *   entity_keys = {
 *     "id" = "invoice_item_id",
 *     "bundle" = "type",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *     "label" = "title",
 *   },
 *   field_ui_base_route = "entity.commerce_invoice_item.bundle_settings"
 * )
 */
class InvoiceItem extends CommerceContentEntityBase implements InvoiceItemInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getInvoice() {
    return $this->getTranslatedReferencedEntity('invoice_id');
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceId() {
    return $this->get('invoice_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->get('description')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->set('description', $description);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormat() {
    return $this->get('description')->format;
  }

  /**
   * {@inheritdoc}
   */
  public function setFormat($format) {
    $this->get('description')->format = $format;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuantity() {
    return (string) $this->get('quantity')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setQuantity($quantity) {
    $this->set('quantity', (string) $quantity);
    $this->recalculateTotalPrice();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnitPrice() {
    if (!$this->get('unit_price')->isEmpty()) {
      return $this->get('unit_price')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setUnitPrice(Price $unit_price) {
    $this->set('unit_price', $unit_price);
    $this->recalculateTotalPrice();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalPrice() {
    if (!$this->get('total_price')->isEmpty()) {
      return $this->get('total_price')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAdjustments(array $adjustment_types = []) {
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
  public function setAdjustments(array $adjustments) {
    $this->set('adjustments', $adjustments);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addAdjustment(Adjustment $adjustment) {
    $this->get('adjustments')->appendItem($adjustment);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeAdjustment(Adjustment $adjustment) {
    $this->get('adjustments')->removeAdjustment($adjustment);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdjustedTotalPrice(array $adjustment_types = []) {
    $total_price = $this->getTotalPrice();
    if (!$total_price) {
      return NULL;
    }

    $adjusted_total_price = $this->applyAdjustments($total_price, $adjustment_types);
    $rounder = \Drupal::service('commerce_price.rounder');
    $adjusted_total_price = $rounder->round($adjusted_total_price);

    return $adjusted_total_price;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdjustedUnitPrice(array $adjustment_types = []) {
    $unit_price = $this->getUnitPrice();
    if (!$unit_price) {
      return NULL;
    }

    $adjusted_total_price = $this->getAdjustedTotalPrice($adjustment_types);
    $adjusted_unit_price = $adjusted_total_price->divide($this->getQuantity());
    $rounder = \Drupal::service('commerce_price.rounder');
    $adjusted_unit_price = $rounder->round($adjusted_unit_price);

    return $adjusted_unit_price;
  }

  /**
   * Applies adjustments to the given price.
   *
   * @param \Drupal\commerce_price\Price $price
   *   The price.
   * @param string[] $adjustment_types
   *   The adjustment types to include in the adjusted price.
   *   Examples: fee, promotion, tax. Defaults to all adjustment types.
   *
   * @return \Drupal\commerce_price\Price
   *   The adjusted price.
   */
  protected function applyAdjustments(Price $price, array $adjustment_types = []) {
    $adjusted_price = $price;
    foreach ($this->getAdjustments($adjustment_types) as $adjustment) {
      if (!$adjustment->isIncluded()) {
        $adjusted_price = $adjusted_price->add($adjustment->getAmount());
      }
    }
    return $adjusted_price;
  }

  /**
   * {@inheritdoc}
   */
  public function getData($key, $default = NULL) {
    $data = [];
    if (!$this->get('data')->isEmpty()) {
      $data = $this->get('data')->first()->getValue();
    }
    return $data[$key] ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setData($key, $value) {
    $this->get('data')->__set($key, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unsetData($key) {
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
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrderItem() {
    return $this->get('order_item_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrderItemId() {
    return $this->get('order_item_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function populateFromOrderItem(OrderItemInterface $order_item) {
    $purchased_entity = $order_item->getPurchasedEntity();
    if ($purchased_entity) {
      $langcode = $this->language()->getId();
      if ($purchased_entity->hasTranslation($langcode)) {
        $purchased_entity = $purchased_entity->getTranslation($langcode);
      }
      $title = $purchased_entity->getOrderItemTitle();
    }
    else {
      $title = $order_item->getTitle();
    }
    $this->set('title', $title);
    // If this is not the default translation, there's no need to reset
    // the untranslatable fields.
    if ($this->isDefaultTranslation()) {
      $this->set('adjustments', $order_item->getAdjustments());
      $this->set('quantity', $order_item->getQuantity());
      $this->set('unit_price', $order_item->getUnitPrice());
      $this->order_item_id = $order_item->id();
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    $this->recalculateTotalPrice();
  }

  /**
   * Recalculates the invoice item total price.
   */
  protected function recalculateTotalPrice() {
    if ($unit_price = $this->getUnitPrice()) {
      $rounder = \Drupal::service('commerce_price.rounder');
      $total_price = $unit_price->multiply($this->getQuantity());
      $this->total_price = $rounder->round($total_price);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // The invoice backreference, populated by Invoice::postSave().
    $fields['invoice_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Invoice'))
      ->setDescription(t('The parent invoice.'))
      ->setSetting('target_type', 'commerce_invoice')
      ->setReadOnly(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The invoice item title.'))
      ->setSettings([
        'default_value' => '',
        'max_length' => 512,
      ])
      ->setTranslatable(TRUE)
      ->setRequired(TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->setTranslatable(TRUE);

    $fields['quantity'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Quantity'))
      ->setDescription(t('The number of purchased units.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setSetting('min', 0)
      ->setDefaultValue(1)
      ->setDisplayOptions('form', [
        'type' => 'commerce_quantity',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['unit_price'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Unit price'))
      ->setDescription(t('The price of a single unit.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'commerce_unit_price',
        'weight' => 2,
        'settings' => [
          'require_confirmation' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['total_price'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Total price'))
      ->setDescription(t('The total price of the invoice item.'))
      ->setReadOnly(TRUE);

    $fields['adjustments'] = BaseFieldDefinition::create('commerce_adjustment')
      ->setLabel(t('Adjustments'))
      ->setRequired(FALSE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'commerce_adjustment_default',
        'weight' => 5,
      ]);

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setDescription(t('A serialized array of additional data.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the invoice item was created.'))
      ->setRequired(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the invoice item was last edited.'))
      ->setRequired(TRUE);

    $fields['order_item_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Order item'))
      ->setDescription(t('The reference to the order item.'))
      ->setSetting('target_type', 'commerce_order_item')
      ->setReadOnly(TRUE);

    return $fields;
  }

}
