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

/**
 * Create the missing Herd Share subscription for order 743.
 *
 * The order was placed from the admin before its card payment was added, so no
 * subscription was created. This does what OrderSubscriber::onPlace() would
 * have done, dated to when the order was placed. The order itself already paid
 * for November 2026, so the new draft recurring order bills December.
 */
function mcgreen_subscription_payment_post_update_create_order_743_subscription() {
  $order_id = 743;
  $customer_id = 270;
  $variation_id = 28;

  $entity_type_manager = \Drupal::entityTypeManager();
  /** @var \Drupal\commerce_order\Entity\OrderInterface|null $order */
  $order = $entity_type_manager->getStorage('commerce_order')->load($order_id);
  if (!$order || (int) $order->getCustomerId() !== $customer_id ||
    in_array($order->getState()->getId(), ['draft', 'canceled'], TRUE)) {
    return t('Skipped: order @id is missing or is not the expected placed order.', ['@id' => $order_id]);
  }

  // Do nothing if someone has already fixed this by hand.
  $subscription_storage = $entity_type_manager->getStorage('commerce_subscription');
  $query = $subscription_storage->getQuery()->accessCheck(FALSE);
  $current = $query->andConditionGroup()
    ->condition('uid', $customer_id)
    ->condition('purchased_entity', $variation_id)
    ->condition('state', ['pending', 'trial', 'active'], 'IN');
  $existing = $query->condition($query->orConditionGroup()
    ->condition('initial_order', $order_id)
    ->condition($current))
    ->execute();
  if ($existing) {
    return t('Skipped: subscription @id already covers order @order.', [
      '@id' => reset($existing),
      '@order' => $order_id,
    ]);
  }

  // From January on, catching up would charge more than one month at once.
  if (\Drupal::time()->getRequestTime() >= strtotime('2027-01-01 00:00:00 America/Chicago')) {
    return t('Skipped: too late to create the subscription for order @id without back-billing. Create it by hand.', ['@id' => $order_id]);
  }

  $order_items = array_filter($order->getItems(), fn($item) => (int) $item->getPurchasedEntityId() === $variation_id);
  $order_item = reset($order_items);
  /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface|null $payment_method */
  $payment_method = $order->get('payment_method')->entity;
  if (!$order_item || !$order_item->getPurchasedEntity() || !$payment_method ||
    !$payment_method->isReusable() || (int) $payment_method->getOwnerId() !== $customer_id) {
    return t('Skipped: order @id lacks the expected item or a reusable payment method. Create the subscription by hand.', ['@id' => $order_id]);
  }
  $variation = $order_item->getPurchasedEntity();

  // Never leave an active subscription behind without its recurring order.
  $transaction = \Drupal::database()->startTransaction();
  try {
    /** @var \Drupal\commerce_recurring\SubscriptionStorageInterface $subscription_storage */
    $subscription = $subscription_storage->createFromOrderItem($order_item, [
      'type' => $variation->get('subscription_type')->target_plugin_id,
      'billing_schedule' => $variation->get('billing_schedule')->entity,
      'initial_order' => $order,
    ]);
    $subscription->setPaymentMethod($payment_method);
    $subscription->setState('active');
    $subscription->setStartTime($order->getPlacedTime());
    $subscription->save();
    $recurring_order = \Drupal::service('commerce_recurring.order_manager')->startRecurring($subscription);
  }
  catch (\Throwable $e) {
    $transaction->rollBack();
    throw $e;
  }

  return t('Created subscription @subscription (quantity @quantity) for order @order, with draft recurring order @recurring.', [
    '@subscription' => $subscription->id(),
    '@quantity' => (float) $subscription->getQuantity(),
    '@order' => $order_id,
    '@recurring' => $recurring_order->id(),
  ]);
}

/**
 * Set orders 674 and 696 to the Zelle gateway they were paid with.
 *
 * Their Zelle payments were recorded while they were drafts, and the refresh on
 * save put the subscription's cash gateway back on the order.
 */
function mcgreen_subscription_payment_post_update_zelle_gateway_orders_674_696() {
  $entity_type_manager = \Drupal::entityTypeManager();
  $order_storage = $entity_type_manager->getStorage('commerce_order');
  /** @var \Drupal\commerce_payment\PaymentStorageInterface $payment_storage */
  $payment_storage = $entity_type_manager->getStorage('commerce_payment');

  $updated = [];
  $skipped = [];
  foreach ([674, 696] as $order_id) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface|null $order */
    $order = $order_storage->load($order_id);
    if (!$order || $order->bundle() !== 'recurring' ||
      $order->getState()->getId() !== 'completed' ||
      $order->get('payment_gateway')->target_id !== 'cash') {
      $skipped[] = $order_id;
      continue;
    }
    // Only change an order whose completed payments were all made with Zelle.
    $gateway_ids = [];
    foreach ($payment_storage->loadMultipleByOrder($order) as $payment) {
      if ($payment->isCompleted()) {
        $gateway_ids[] = $payment->getPaymentGatewayId();
      }
    }
    if (array_unique($gateway_ids) !== ['zelle']) {
      $skipped[] = $order_id;
      continue;
    }
    $order->set('payment_gateway', 'zelle');
    $order->save();
    $updated[] = $order_id;
  }

  return t('Set the Zelle gateway on orders: @updated. Skipped (missing or not as expected): @skipped.', [
    '@updated' => implode(', ', $updated) ?: 'none',
    '@skipped' => implode(', ', $skipped) ?: 'none',
  ]);
}
