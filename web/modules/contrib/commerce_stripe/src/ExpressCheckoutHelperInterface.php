<?php

namespace Drupal\commerce_stripe;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Provides an interface for the Express Checkout helper.
 */
interface ExpressCheckoutHelperInterface {

  /**
   * Gets an array of order line items.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return array
   *   An array of order line items.
   */
  public function getOrderLineItems(OrderInterface $order): array;

}
