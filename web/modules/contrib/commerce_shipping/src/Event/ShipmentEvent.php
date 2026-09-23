<?php

namespace Drupal\commerce_shipping\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_shipping\Entity\ShipmentInterface;

/**
 * Defines the shipment event.
 *
 * @see \Drupal\commerce_shipping\Event\ShippingEvents
 */
class ShipmentEvent extends EventBase {

  /**
   * Constructs a new ShipmentEvent.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   */
  public function __construct(protected ShipmentInterface $shipment) {}

  /**
   * Gets the shipment.
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface
   *   Gets the shipment.
   */
  public function getShipment(): ShipmentInterface {
    return $this->shipment;
  }

}
