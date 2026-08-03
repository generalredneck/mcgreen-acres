<?php

namespace Drupal\mcgreen_order_payment\Mail;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Sends the "your invoice is ready for payment" email.
 */
interface PaymentRequestMailInterface {

  /**
   * Sends the payment request email for an order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order, already prepared for direct payment.
   * @param string $payment_url
   *   The absolute URL the customer should visit to pay.
   * @param int $deadline_timestamp
   *   The timestamp by which payment is requested.
   *
   * @return bool
   *   TRUE if the email was sent.
   */
  public function send(OrderInterface $order, string $payment_url, int $deadline_timestamp): bool;

}
