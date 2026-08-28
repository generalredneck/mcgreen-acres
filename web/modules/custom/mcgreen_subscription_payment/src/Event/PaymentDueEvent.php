<?php

namespace Drupal\mcgreen_subscription_payment\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Fired when a recurring order for a manual-payment subscription is due.
 */
class PaymentDueEvent extends EventBase {

  /**
   * The recurring order awaiting manual payment.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  public function __construct(OrderInterface $order) {
    $this->order = $order;
  }

  /**
   * Gets the recurring order awaiting manual payment.
   */
  public function getOrder(): OrderInterface {
    return $this->order;
  }

}
