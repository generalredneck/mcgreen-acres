<?php

namespace Drupal\commerce_invoice;

use Drupal\commerce_invoice\Entity\InvoiceInterface;

interface InvoiceTotalSummaryInterface {

  /**
   * Builds the totals for the given invoice.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice
   *   The invoice.
   *
   * @return array
   *   An array of totals with the following elements:
   *     - subtotal: The order subtotal price.
   *     - adjustments: The adjustments:
   *         - type: The adjustment type.
   *         - label: The adjustment label.
   *         - amount: The adjustment amount.
   *         - percentage: The decimal adjustment percentage, when available.
   *     - total: The invoice total price.
   */
  public function buildTotals(InvoiceInterface $invoice);

}
