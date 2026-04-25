<?php

namespace Drupal\commerce_stripe;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderTotalSummaryInterface;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a helper for Express Checkout.
 */
class ExpressCheckoutHelper implements ExpressCheckoutHelperInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new ExpressCheckoutHelper object.
   *
   * @param \Drupal\commerce_price\MinorUnitsConverterInterface $minorUnitsConverter
   *   The minor units converter.
   * @param \Drupal\commerce_order\OrderTotalSummaryInterface $orderTotalSummary
   *   The order total summary service.
   */
  public function __construct(
    protected MinorUnitsConverterInterface $minorUnitsConverter,
    protected OrderTotalSummaryInterface $orderTotalSummary,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getOrderLineItems(OrderInterface $order): array {
    $line_items = [];

    foreach ($order->getItems() as $order_item) {
      $line_items[] = [
        'name' => $order_item->getTitle(),
        'amount' => $this->minorUnitsConverter->toMinorUnits($order_item->getTotalPrice()),
      ];
    }

    $order_total_summary = $this->orderTotalSummary->buildTotals($order);
    if (empty($order_total_summary['adjustments'])) {
      return $line_items;
    }

    $extra_line_items = [];
    foreach ($order_total_summary['adjustments'] as $adjustment) {
      // The adjustment is a tax and already included in the price of the line
      // items. Continue:
      if ($adjustment['type'] === 'tax' && !empty($adjustment['included'])) {
        continue;
      }
      $extra_line_items[] = [
        'name' => $adjustment['label'],
        'amount' => $this->minorUnitsConverter->toMinorUnits($adjustment['amount']),
      ];
    }

    return array_merge($line_items, $extra_line_items);
  }

}
