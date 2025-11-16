<?php

namespace Drupal\mcgreen_acres_store\EventSubscriber;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 *
 */
class OrderUpdateSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CheckoutEvents::COMPLETION => ['onOrderPresave', 999],
    ];
  }

  /**
   * Reacts to an order presave event.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onOrderPresave(OrderEvent $event) {
    $order = $event->getOrder();
    foreach ($order->getItems() as $orderItem) {
      $purchasedEntity = $orderItem->getPurchasedEntity();
      if ($purchasedEntity instanceof ProductVariationInterface) {
        $purchasedEntity->save();
      }
    }
  }

}
