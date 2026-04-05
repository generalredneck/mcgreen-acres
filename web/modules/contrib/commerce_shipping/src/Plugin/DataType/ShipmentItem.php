<?php

namespace Drupal\commerce_shipping\Plugin\DataType;

use Drupal\commerce_shipping\TypedData\ShipmentItemDataDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\TypedData;

/**
 * Defines a data type for shipment items.
 */
#[DataType(
  id: 'shipment_item',
  label: new TranslatableMarkup('Shipment item'),
  description: new TranslatableMarkup('Shipment item'),
  definition_class: ShipmentItemDataDefinition::class,
)]
final class ShipmentItem extends TypedData {

  /**
   * The data value.
   *
   * @var \Drupal\commerce_shipping\ShipmentItem
   */
  protected $value;

  /**
   * Gets the array representation of the shipment item.
   *
   * @return array|null
   *   The array.
   */
  public function toArray(): ?array {
    return $this->value?->toArray();
  }

}
