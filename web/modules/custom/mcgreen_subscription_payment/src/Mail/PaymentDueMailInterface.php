<?php

namespace Drupal\mcgreen_subscription_payment\Mail;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Sends "payment due" emails for manual-payment subscription renewals.
 */
interface PaymentDueMailInterface {

  /**
   * Sends a payment due email for a manual-payment recurring order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The placed recurring order awaiting manual payment.
   *
   * @return bool
   *   TRUE if the email was sent successfully, FALSE otherwise.
   */
  public function send(OrderInterface $order): bool;

}
