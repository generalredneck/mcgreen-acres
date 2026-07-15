<?php

namespace Drupal\mcgreen_acres_store\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Auto-completes orders that don't need appointment fulfillment.
 *
 * Placing a "default" bundle order always moves it from draft into the
 * "fulfillment" state first (see the order_fulfillment workflow). This
 * subscriber immediately fulfills it from there unless it actually needs to
 * wait on a pickup/delivery: either because the customer entered checkout
 * with something not available at the farm stand, or because staff marked
 * a manually-entered order as needing fulfillment.
 */
class OrderFulfillmentSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.place.post_transition' => 'onPlace',
    ];
  }

  /**
   * Reacts to an order being placed.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function onPlace(WorkflowTransitionEvent $event) {
    $order = $event->getEntity();
    if (!$order instanceof OrderInterface || $order->bundle() !== 'default') {
      return;
    }
    if ($order->getState()->getId() !== 'fulfillment') {
      // Only relevant to order types using the order_fulfillment workflow.
      return;
    }

    if (!_mcgreen_acres_store_order_needs_fulfillment($order)) {
      $order->getState()->applyTransitionById('fulfill');
      $order->save();
    }
  }

}
