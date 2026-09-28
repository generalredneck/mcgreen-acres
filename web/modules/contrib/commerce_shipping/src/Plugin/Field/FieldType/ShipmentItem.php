<?php

namespace Drupal\commerce_shipping\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\commerce_shipping\ShipmentItem as ShipmentItemValue;

/**
 * Plugin implementation of the 'commerce_shipment_item' field type.
 *
 * @property mixed $value
 */
#[FieldType(
  id: 'commerce_shipment_item',
  label: new TranslatableMarkup('Shipment item'),
  description: new TranslatableMarkup('Stores shipment items'),
  category: 'commerce',
  default_widget: 'commerce_shipment_item_default',
  no_ui: TRUE,
  list_class: ShipmentItemList::class,
)]
class ShipmentItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('shipment_item')
      ->setLabel(t('Value'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return !$this->value instanceof ShipmentItemValue;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (is_array($values)) {
      // The property definition causes the shipment item to be in 'value' key.
      $values = reset($values);
    }
    if (!$values instanceof ShipmentItemValue) {
      $values = NULL;
    }
    parent::setValue($values, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'description' => 'The shipment item value.',
          'type' => 'blob',
          'not null' => TRUE,
          'serialize' => TRUE,
        ],
      ],
    ];
  }

}
