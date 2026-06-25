<?php

namespace Drupal\commerce_invoice;

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\AdjustmentTransformerInterface;

/**
 * Provides the invoice total summary.
 */
class InvoiceTotalSummary implements InvoiceTotalSummaryInterface {

  /**
   * Constructs a new InvoiceTotalSummary object.
   *
   * @param \Drupal\commerce_order\AdjustmentTransformerInterface $adjustmentTransformer
   *   The adjustment transformer.
   */
  public function __construct(protected AdjustmentTransformerInterface $adjustmentTransformer) {}

  /**
   * {@inheritdoc}
   */
  public function buildTotals(InvoiceInterface $invoice) {
    $adjustments = $invoice->collectAdjustments();
    $adjustments = $this->adjustmentTransformer->processAdjustments($adjustments);
    // Included adjustments are not displayed to the customer, they
    // exist to allow the developer to know what the price is made of.
    // The one exception is taxes, which need to be shown for legal reasons.
    $adjustments = array_filter($adjustments, function (Adjustment $adjustment) {
      return $adjustment->getType() === 'tax' || !$adjustment->isIncluded();
    });
    // Convert the adjustments to arrays.
    $adjustments = array_map(function (Adjustment $adjustment) {
      return $adjustment->toArray();
    }, $adjustments);
    // Provide the "total" key for backwards compatibility reasons.
    foreach ($adjustments as $index => $adjustment) {
      $adjustments[$index]['total'] = $adjustments[$index]['amount'];
    }

    return [
      'subtotal' => $invoice->getSubtotalPrice(),
      'adjustments' => $adjustments,
      'total' => $invoice->getTotalPrice(),
    ];
  }

}
