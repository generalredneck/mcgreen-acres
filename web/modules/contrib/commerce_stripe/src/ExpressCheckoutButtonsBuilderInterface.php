<?php

namespace Drupal\commerce_stripe;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;

/**
 * Provides an interface for the Express Checkout buttons builder.
 */
interface ExpressCheckoutButtonsBuilderInterface {

  /**
   * Builds the Express Checkout buttons.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway
   *   The payment gateway.
   *
   * @return array
   *   A renderable array representing the Express Checkout buttons.
   */
  public function build(OrderInterface $order, PaymentGatewayInterface $payment_gateway): array;

}
