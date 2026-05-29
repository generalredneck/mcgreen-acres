<?php

/**
 * @file
 * Post-update hooks for mcgreen_subscription_payment.
 */

/**
 * Create missing renewal draft orders for manually-completed recurring orders.
 *
 * When an admin marks a recurring order as paid before cron's billing-period
 * expiry check fires, the queue-based renew job is never created. This finds
 * all such orphaned completed orders and calls renewOrder() for each one whose
 * subscription is still active.
 */
function mcgreen_subscription_payment_post_update_renew_manually_completed_orders(&$sandbox) {
  $order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
  $order_manager = \Drupal::service('commerce_recurring.order_manager');

  $order_ids = $order_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'recurring')
    ->condition('state', 'completed')
    ->condition('commerce_recurring_queued', 0)
    ->execute();

  if (!$order_ids) {
    return t('No manually-completed recurring orders found; nothing to do.');
  }

  $renewed = 0;
  foreach ($order_storage->loadMultiple($order_ids) as $order) {
    $subscriptions = $order_manager->collectSubscriptions($order);
    $active = array_filter($subscriptions, fn($s) => $s->getState()->getId() === 'active');
    if (!$active) {
      continue;
    }
    $order_manager->renewOrder($order);
    $renewed++;
  }

  return \Drupal::translation()->formatPlural(
    $renewed,
    'Created renewal draft for 1 recurring order.',
    'Created renewal drafts for @count recurring orders.'
  );
}
