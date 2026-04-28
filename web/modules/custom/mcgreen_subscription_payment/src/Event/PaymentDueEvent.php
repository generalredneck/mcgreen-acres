<?php

namespace Drupal\mcgreen_subscription_payment\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Fired when a recurring order for a manual-payment subscription needs payment.
 */
class PaymentDueEvent extends EventBase {

  /**
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  public function __construct(OrderInterface $order) {
    $this->order = $order;
  }

  public function getOrder(): OrderInterface {
    return $this->order;
  }

}
