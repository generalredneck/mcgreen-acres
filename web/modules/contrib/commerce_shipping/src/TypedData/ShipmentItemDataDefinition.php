<?php

namespace Drupal\commerce_shipping\TypedData;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Data definition for the ShipmentItemDataDefinition data type.
 */
class ShipmentItemDataDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    $properties = [];

    $properties['order_item_id'] = DataDefinition::create('integer')
      ->setRequired(TRUE)
      ->setLabel("The source order item ID.");

    $properties['title'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel("The title.");

    $properties['quantity'] = DataDefinition::create('string')
      ->setLabel("The quantity.")
      ->setRequired(TRUE);

    $properties['weight'] = DataDefinition::create('any')
      ->setLabel("The weight.")
      ->setRequired(TRUE);

    $properties['declared_value'] = DataDefinition::create('any')
      ->setLabel("The declared value.")
      ->setRequired(TRUE);

    $properties['tariff_code'] = DataDefinition::create('string')
      ->setLabel("The tariff code.")
      ->setRequired(TRUE);

    return $properties;
  }

}
