<?php

namespace Drupal\mcgreen_subscription_payment\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\ManualPaymentGatewayInterface;
use Drupal\commerce_recurring\EventSubscriber\OrderSubscriber as BaseOrderSubscriber;
use Drupal\state_machine\Event\WorkflowTransitionEvent;

/**
 * Extends the core recurring order subscriber to support manual gateways.
 *
 * The parent implementation skips subscription creation when no stored payment
 * method is present and the billing schedule does not allow trials. This class
 * relaxes that guard for manual payment gateways (cash, check, etc.) and
 * records the gateway ID directly on the subscription so the recurring order
 * manager can handle renewals without a payment method entity.
 */
class OrderSubscriber extends BaseOrderSubscriber {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events['commerce_order.mark_paid.pre_transition'] = 'onRecurringOrderMarkPaid';
    return $events;
  }

  /**
   * Triggers renewal when a recurring order is manually marked paid.
   *
   * Cron sets commerce_recurring_queued=TRUE before enqueuing the renew job, so
   * we only act when that flag is FALSE — meaning the admin completed the order
   * before cron's billing-period-expiry check could fire the queue-based renewal.
   */
  public function onRecurringOrderMarkPaid(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    if ($order->bundle() !== 'recurring') {
      return;
    }
    // Cron already queued a renew job — let the queue handle it.
    if ($order->get('commerce_recurring_queued')->value) {
      return;
    }
    $this->recurringOrderManager->renewOrder($order);
  }

  /**
   * {@inheritdoc}
   */
  public function onPlace(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_recurring\SubscriptionStorageInterface $subscription_storage */
    $subscription_storage = $this->entityTypeManager->getStorage('commerce_subscription');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    if ($order->bundle() == 'recurring') {
      return;
    }

    $payment_method = $order->get('payment_method')->entity;
    $manual_gateway = $this->getManualGateway($order);
    $start_time = $this->time->getRequestTime();

    foreach ($order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if (!$purchased_entity || !$purchased_entity->hasField('subscription_type')) {
        continue;
      }
      $subscription_type_item = $purchased_entity->get('subscription_type');
      $billing_schedule_item = $purchased_entity->get('billing_schedule');
      if ($subscription_type_item->isEmpty() || $billing_schedule_item->isEmpty()) {
        continue;
      }
      /** @var \Drupal\commerce_recurring\Entity\BillingScheduleInterface $billing_schedule */
      $billing_schedule = $billing_schedule_item->entity;

      // Original guard: skip if no trial, no payment method — but now also
      // allow through when a manual gateway is present.
      if (!$billing_schedule->getPlugin()->allowTrials() && empty($payment_method) && !$manual_gateway) {
        continue;
      }

      $subscription = $subscription_storage->createFromOrderItem($order_item, [
        'type' => $subscription_type_item->target_plugin_id,
        'billing_schedule' => $billing_schedule,
        'initial_order' => $order,
      ]);

      if (($payment_method instanceof PaymentMethodInterface) && $payment_method->isReusable()) {
        $subscription->setPaymentMethod($payment_method);
      }
      elseif ($manual_gateway) {
        // Store the manual gateway on the subscription so renewals know how to
        // handle billing without a stored payment method.
        $subscription->set('manual_payment_gateway', $manual_gateway->id());
      }

      if ($billing_schedule->getPlugin()->allowTrials()) {
        $subscription->setState('trial');
        $subscription->setTrialStartTime($start_time);
        $subscription->save();
        $this->recurringOrderManager->startTrial($subscription);
      }
      else {
        $subscription->setState('active');
        $subscription->setStartTime($start_time);
        $subscription->save();
        $this->recurringOrderManager->startRecurring($subscription);
      }
    }
  }

  /**
   * Returns the manual payment gateway entity from the order, or NULL.
   */
  protected function getManualGateway(OrderInterface $order) {
    $gateway_entity = $order->get('payment_gateway')->entity;
    if ($gateway_entity && $gateway_entity->getPlugin() instanceof ManualPaymentGatewayInterface) {
      return $gateway_entity;
    }
    return NULL;
  }

}
