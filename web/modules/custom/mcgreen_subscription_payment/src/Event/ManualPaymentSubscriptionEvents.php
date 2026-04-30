<?php

namespace Drupal\mcgreen_subscription_payment\Event;

final class ManualPaymentSubscriptionEvents {

  /**
   * Fired when a recurring order for a manual-payment subscription is placed
   * and awaiting offline payment (cash, check, etc.).
   *
   * Subscribers can send a "payment due" notification to the customer.
   *
   * @Event
   *
   * @see \Drupal\mcgreen_subscription_payment\Event\PaymentDueEvent
   */
  const PAYMENT_DUE = 'mcgreen_subscription_payment.payment_due';

}
