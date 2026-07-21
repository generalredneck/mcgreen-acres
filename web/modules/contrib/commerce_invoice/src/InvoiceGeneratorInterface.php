<?php

namespace Drupal\commerce_invoice;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\profile\Entity\ProfileInterface;

interface InvoiceGeneratorInterface {

  /**
   * Generates an invoice for the given orders.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface[] $orders
   *   The orders to generate an invoice for.
   * @param \Drupal\commerce_Store\Entity\StoreInterface $store
   *   The store.
   * @param \Drupal\profile\Entity\ProfileInterface|null $profile
   *   (optional) The billing profile.
   * @param array $values
   *   (optional) An array of values to set on the invoice,
   *   keyed by property name.
   * @param bool $save
   *   (optional) Whether to save the generated invoice and its items or not.
   *   Defaults to TRUE.
   *
   * @return \Drupal\commerce_invoice\Entity\InvoiceInterface|null
   *   The generated invoice, NULL if it could not be generated.
   */
  public function generate(array $orders, StoreInterface $store, ?ProfileInterface $profile = NULL, array $values = [], $save = TRUE);

}
