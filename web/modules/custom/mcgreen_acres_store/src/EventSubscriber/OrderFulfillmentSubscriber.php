<?php

namespace Drupal\mcgreen_acres_store\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Auto-completes orders that don't need appointment fulfillment.
 *
 * Placing a "default" bundle order always moves it from draft into the
 * "fulfillment" state first (see the order_fulfillment workflow). This
 * resolves field_needs_fulfillment and, if nothing is left to wait on,
 * immediately fulfills the order from there.
 *
 * This used to run as an event subscriber on
 * commerce_order.place.post_transition and call $order->save() from
 * inside that handler. That's unsafe: the state field's transition
 * bookkeeping (StateItem::$transitionToApply / $originalValue) isn't
 * reset until *after* post_transition dispatch finishes, so a save
 * triggered from inside it sees the still-pending "place" transition and
 * replays commerce_order.place.post_transition a second time - double
 * order-confirmation emails, double commerce_reports entries, etc.
 * (Confirmed via the order activity log showing two "Place order"
 * transitions and two receipt-email attempts per checkout.) This is now
 * invoked from hook_commerce_order_insert()/update() instead, which run
 * after the entity's full save lifecycle (including field postSave())
 * has completed, so a save from here is safe.
 */
class OrderFulfillmentSubscriber {

  /**
   * Reacts to an order being saved.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The saved order.
   */
  public function reactToOrderSave(OrderInterface $order) {
    if ($order->bundle() !== 'default') {
      return;
    }
    if ($order->getState()->getId() !== 'fulfillment') {
      // Only relevant to order types using the order_fulfillment workflow,
      // and only while still sitting in the fulfillment state - once it
      // moves to completed (or anywhere else) there's nothing left to do.
      return;
    }

    $needs_fulfillment = _mcgreen_acres_store_order_needs_fulfillment($order);
    $save = FALSE;

    // Nothing has explicitly answered yet - most notably Express
    // Checkout, which bypasses the PickupTiming pane (and every other
    // checkout step) entirely. Persist the resolved answer so the
    // receipt and admin views show something real instead of nothing.
    // This re-derives from the cart rather than assuming Express always
    // means "today": Express is only ever offered for an all-farm-stand
    // cart, but if a mixed cart somehow reached this point with the
    // field still unset, it must still resolve to TRUE here, never be
    // forced to FALSE just because of how it got placed.
    if ($order->hasField('field_needs_fulfillment') && $order->get('field_needs_fulfillment')->isEmpty()) {
      $order->set('field_needs_fulfillment', $needs_fulfillment);
      $save = TRUE;
    }

    if (!$needs_fulfillment) {
      $order->getState()->applyTransitionById('fulfill');
      $save = TRUE;
    }

    if ($save) {
      $order->save();
    }
  }

}
