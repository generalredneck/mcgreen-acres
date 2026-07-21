<?php

namespace Drupal\commerce_recurring_log\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ReactivateWithImmediatePaymentEvent extends Event {
  const REACTIVATE_WITH_IMMEDIATE_PAYMENT = 'commerce_recurring_log.reactivate_with_immediate_payment';

  protected $subscription;

  public function __construct($subscription) {
    $this->subscription = $subscription;
  }

  public function getSubscription() {
    return $this->subscription;
  }
}
