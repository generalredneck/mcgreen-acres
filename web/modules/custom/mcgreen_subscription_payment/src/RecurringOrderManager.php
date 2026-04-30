<?php

namespace Drupal\mcgreen_subscription_payment;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_recurring\BillingPeriod;
use Drupal\commerce_recurring\Entity\BillingSchedule;
use Drupal\commerce_recurring\Entity\SubscriptionInterface;
use Drupal\commerce_recurring\RecurringOrderManager as BaseRecurringOrderManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcgreen_subscription_payment\Event\ManualPaymentSubscriptionEvents;
use Drupal\mcgreen_subscription_payment\Event\PaymentDueEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Extends RecurringOrderManager to handle manual payment gateway subscriptions.
 *
 * When no stored payment method exists but the subscription has a manual
 * payment gateway set, closeOrder() dispatches a PAYMENT_DUE event (triggering
 * a "payment due" email) rather than throwing HardDeclineException. This allows
 * the store to notify the customer that they owe money and collect it offline.
 *
 * refreshOrder() and findOrCreateOrder() are also extended to populate the
 * payment_gateway field on the recurring order from the subscription's manual
 * gateway, keeping the order data consistent.
 */
class RecurringOrderManager extends BaseRecurringOrderManager {

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, TimeInterface $time, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($entity_type_manager, $time);
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   *
   * Overridden to dispatch PAYMENT_DUE instead of HardDeclineException when
   * the subscription uses a manual payment gateway.
   */
  public function closeOrder(OrderInterface $order) {
    $order_state = $order->getState()->getId();

    if ($order->isPaid()) {
      if (in_array('mark_paid', array_keys($order->getState()->getTransitions()))) {
        $order->getState()->applyTransitionById('mark_paid');
        $order->save();
      }
    }
    if (in_array($order_state, ['canceled', 'completed']) || $order->isPaid()) {
      return;
    }

    if ($order_state == 'draft') {
      $order->getState()->applyTransitionById('place');
      $order->save();
    }

    $subscriptions = $this->collectSubscriptions($order);
    $payment_method = $this->selectPaymentMethod($subscriptions);

    if (!$payment_method) {
      // For manual-gateway subscriptions: order is already placed above.
      // Fire the payment-due event so a notification email can be sent, then
      // return cleanly — no exception, no dunning cycle.
      $manual_gateway = $this->selectManualGateway($subscriptions);
      if ($manual_gateway) {
        $event = new PaymentDueEvent($order);
        $this->eventDispatcher->dispatch($event, ManualPaymentSubscriptionEvents::PAYMENT_DUE);
        return;
      }
      throw new HardDeclineException('Payment method not found.');
    }

    $payment_gateway = $payment_method->getPaymentGateway();
    if (!$payment_gateway) {
      throw new HardDeclineException('Payment gateway not found');
    }

    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    if (!$order->isPaid()) {
      $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
      $payment = $payment_storage->create([
        'payment_gateway' => $payment_gateway->id(),
        'payment_method' => $payment_method->id(),
        'order_id' => $order,
        'amount' => $order->getTotalPrice(),
        'state' => 'new',
      ]);
      $payment_gateway_plugin->createPayment($payment);

      if ($order->getState()->isTransitionAllowed('mark_paid')) {
        $order->getState()->applyTransitionById('mark_paid');
      }
      $order->save();
    }
  }

  /**
   * {@inheritdoc}
   *
   * Overridden to set payment_gateway from the manual gateway when no stored
   * payment method exists, so the recurring order record stays accurate.
   */
  public function refreshOrder(OrderInterface $order) {
    /** @var \Drupal\commerce_recurring\Plugin\Field\FieldType\BillingPeriodItem $billing_period_item */
    $billing_period_item = $order->get('billing_period')->first();
    $billing_period = $billing_period_item->toBillingPeriod();
    $subscriptions = $this->collectSubscriptions($order);
    $payment_method = $this->selectPaymentMethod($subscriptions);
    $billing_profile = $payment_method ? $payment_method->getBillingProfile() : $order->getBillingProfile();
    $payment_gateway_id = $payment_method
      ? $payment_method->getPaymentGatewayId()
      : $this->selectManualGatewayId($subscriptions);

    $order->set('billing_profile', $billing_profile);
    $order->set('payment_method', $payment_method);
    $order->set('payment_gateway', $payment_gateway_id);

    foreach ($subscriptions as $subscription) {
      $this->applyCharges($order, $subscription, $billing_period);
    }

    $order_items = $order->getItems();
    if (!$order_items) {
      $order->set('state', 'canceled');
    }
    foreach ($order_items as $order_item) {
      if ($order_item->isNew()) {
        $order_item->order_id->entity = $order;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Overridden to populate payment_gateway from the manual gateway on the
   * newly-created recurring order when no stored payment method is present.
   */
  protected function findOrCreateOrder(SubscriptionInterface $subscription, BillingPeriod $billing_period) {
    $billing_schedule = $subscription->getBillingSchedule();
    assert($billing_schedule instanceof BillingSchedule);
    $payment_method = $subscription->getPaymentMethod();
    $manual_gateway_id = $subscription->get('manual_payment_gateway')->target_id;
    $effective_gateway_id = $payment_method ? $payment_method->getPaymentGatewayId() : $manual_gateway_id;

    if ($billing_schedule->allowCombiningSubscriptions()) {
      $order_storage = $this->entityTypeManager->getStorage('commerce_order');
      $query = $order_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'recurring')
        ->condition('state', 'draft')
        ->condition('store_id', $subscription->getStoreId())
        ->condition('uid', $subscription->getCustomerId())
        ->condition('billing_schedule', $billing_schedule->id())
        ->condition('billing_period.starts', $billing_period->getStartDate()->getTimestamp(), '=')
        ->condition('billing_period.ends', $billing_period->getEndDate()->getTimestamp(), '=');

      if ($payment_method) {
        $query->condition('payment_method', $payment_method->id());
      }

      $existing = $query->execute();
      if ($existing) {
        $existing_recurring_order = $order_storage->load(reset($existing));
        assert($existing_recurring_order instanceof OrderInterface);
        return $existing_recurring_order;
      }
    }

    return $this->entityTypeManager->getStorage('commerce_order')->create([
      'type' => 'recurring',
      'store_id' => $subscription->getStoreId(),
      'uid' => $subscription->getCustomerId(),
      'billing_profile' => $payment_method ? $payment_method->getBillingProfile() : NULL,
      'payment_method' => $payment_method,
      'payment_gateway' => $effective_gateway_id,
      'billing_period' => $billing_period,
      'billing_schedule' => $subscription->getBillingSchedule(),
    ]);
  }

  /**
   * Returns the manual payment gateway entity for the given subscriptions.
   *
   * @param \Drupal\commerce_recurring\Entity\SubscriptionInterface[] $subscriptions
   *
   * @return \Drupal\commerce_payment\Entity\PaymentGatewayInterface|null
   */
  protected function selectManualGateway(array $subscriptions) {
    $gateway_id = $this->selectManualGatewayId($subscriptions);
    if (!$gateway_id) {
      return NULL;
    }
    return $this->entityTypeManager->getStorage('commerce_payment_gateway')->load($gateway_id);
  }

  /**
   * Returns the manual payment gateway ID for the given subscriptions, or NULL.
   *
   * @param \Drupal\commerce_recurring\Entity\SubscriptionInterface[] $subscriptions
   */
  protected function selectManualGatewayId(array $subscriptions): ?string {
    foreach ($subscriptions as $subscription) {
      $gateway_id = $subscription->get('manual_payment_gateway')->target_id;
      if ($gateway_id) {
        return $gateway_id;
      }
    }
    return NULL;
  }

}
