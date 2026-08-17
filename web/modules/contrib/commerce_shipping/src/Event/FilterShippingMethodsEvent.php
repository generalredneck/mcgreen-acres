<?php

namespace Drupal\commerce_shipping\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_shipping\Entity\ShipmentInterface;

/**
 * Defines the event for filtering the available shipping methods.
 *
 * @see \Drupal\commerce_shipping\Event\ShippingEvents
 */
class FilterShippingMethodsEvent extends EventBase {

  /**
   * Constructs a new FilterShippingMethodsEvent object.
   *
   * @param \Drupal\commerce_shipping\Entity\ShippingMethodInterface[] $shippingMethods
   *   The shipping methods.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   */
  public function __construct(protected array $shippingMethods, protected ShipmentInterface $shipment) {}

  /**
   * Gets the shipping methods.
   *
   * @return \Drupal\commerce_shipping\Entity\ShippingMethodInterface[]
   *   The shipping methods.
   */
  public function getShippingMethods(): array {
    return $this->shippingMethods;
  }

  /**
   * Sets the shipping methods.
   *
   * @param \Drupal\commerce_shipping\Entity\ShippingMethodInterface[] $shipping_methods
   *   The shipping methods.
   *
   * @return $this
   */
  public function setShippingMethods(array $shipping_methods): static {
    $this->shippingMethods = $shipping_methods;
    return $this;
  }

  /**
   * Gets the shipment.
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface
   *   The shipment.
   */
  public function getShipment(): ShipmentInterface {
    return $this->shipment;
  }

}
