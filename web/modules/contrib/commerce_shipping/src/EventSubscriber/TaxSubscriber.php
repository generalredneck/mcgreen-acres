<?php

namespace Drupal\commerce_shipping\EventSubscriber;

use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\commerce_tax\Event\CustomerProfileEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Overrides the address used for calculating taxes.
 */
class TaxSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new TaxSubscriber object.
   *
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shippingOrderManager
   *   The shipping order manager.
   */
  public function __construct(protected ShippingOrderManagerInterface $shippingOrderManager) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_tax.customer_profile' => ['onCustomerProfile'],
    ];
  }

  /**
   * Overrides the address used for calculating tax.
   *
   * By default, TaxTypeBase::buildCustomerProfile() will select the
   * shipping address when available (thanks to shipping's ProfileSubscriber).
   *
   * This subscriber extends the default logic to support orders with multiple
   * shipping addresses (multiple shipments with distinct shipping profiles).
   *
   * @param \Drupal\commerce_tax\Event\CustomerProfileEvent $event
   *   The transition event.
   */
  public function onCustomerProfile(CustomerProfileEvent $event): void {
    $order_item = $event->getOrderItem();
    $order = $order_item->getOrder();
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();
    $shipping_profiles = [];
    foreach ($shipments as $shipment) {
      $shipping_profile = $shipment->getShippingProfile();
      if ($shipping_profile) {
        $shipping_profiles[$shipping_profile->id()] = $shipping_profile;
      }
    }
    if (count($shipping_profiles) < 2) {
      // Multiple profiles were not found, fall back to the default logic.
      return;
    }

    $customer_profile = $event->getCustomerProfile();
    foreach ($shipments as $shipment) {
      foreach ($shipment->getItems() as $shipment_item) {
        // Take the address from the shipment which contains the given item.
        if ($shipment_item->getOrderItemId() == $order_item->id()) {
          $address_field = $shipment->getShippingProfile()->get('address');
          $customer_profile->set('address', $address_field->getValue());
          return;
        }
      }
    }
  }

}
