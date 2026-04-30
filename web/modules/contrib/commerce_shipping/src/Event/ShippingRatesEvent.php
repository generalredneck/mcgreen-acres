<?php

namespace Drupal\commerce_shipping\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;

/**
 * Defines the event for reacting to shipping rate calculation.
 *
 * @see \Drupal\commerce_shipping\Event\ShippingEvents
 */
class ShippingRatesEvent extends EventBase {

  /**
   * Constructs a new ShippingRatesEvent.
   *
   * @param \Drupal\commerce_shipping\ShippingRate[] $rates
   *   The shipping rates.
   * @param \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shippingMethod
   *   The shipping method calculating the rates.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   */
  public function __construct(
    protected array $rates,
    protected ShippingMethodInterface $shippingMethod,
    protected ShipmentInterface $shipment,
  ) {}

  /**
   * Gets the shipping rates.
   *
   * @return \Drupal\commerce_shipping\ShippingRate[]
   *   The shipping rates.
   */
  public function getRates(): array {
    return $this->rates;
  }

  /**
   * Sets the shipping rates.
   *
   * @param \Drupal\commerce_shipping\ShippingRate[] $rates
   *   The shipping rates.
   */
  public function setRates(array $rates): static {
    $this->rates = $rates;
    return $this;
  }

  /**
   * Gets the shipping method.
   *
   * @return \Drupal\commerce_shipping\Entity\ShippingMethodInterface
   *   The shipping method.
   */
  public function getShippingMethod(): ShippingMethodInterface {
    return $this->shippingMethod;
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
