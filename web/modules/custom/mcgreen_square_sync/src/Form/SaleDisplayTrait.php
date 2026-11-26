<?php

namespace Drupal\mcgreen_square_sync\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Link;
use Drupal\mcgreen_square_sync\SalesReport;

/**
 * How Square sales are written out on the admin pages.
 *
 * Classes using this need a "currencyFormatter" property.
 */
trait SaleDisplayTrait {

  /**
   * Describes how a sale was paid.
   */
  protected function paidBy(array $sale): string {
    $parts = [];
    $types = [
      'card' => $this->t('Card'),
      'cash' => $this->t('Cash'),
      'other' => $this->t('Other'),
    ];
    foreach ($types as $column => $label) {
      if ($sale[$column]) {
        $parts[$label->render()] = $sale[$column];
      }
    }
    if (count($parts) > 1) {
      foreach ($parts as $label => $amount) {
        $parts[$label] = $label . ' ' . $this->money($amount, $sale['currency']);
      }
      $text = implode(' + ', $parts);
    }
    else {
      $text = (string) array_key_first($parts);
    }
    if ($sale['refunded']) {
      $text .= ' ' . $this->t('(refunded @amount)', ['@amount' => $this->money($sale['refunded'], $sale['currency'])]);
    }
    return $text;
  }

  /**
   * Describes one line item.
   */
  protected function itemLabel(array $item, string $currency): string {
    if ($this->isCustomAmount($item)) {
      $name = $this->t('Custom amount')->render();
      $detail = $item['note'] ?: $item['name'];
      if ($detail && $detail !== 'Custom Amount') {
        $name .= ': ' . $detail;
      }
    }
    else {
      $name = $item['name'];
      if ($item['variation_name'] && !in_array($item['variation_name'], ['Regular', $item['name']], TRUE)) {
        $name .= ' (' . $item['variation_name'] . ')';
      }
    }
    return $item['quantity'] . ' × ' . $name . ' – ' . $this->money($item['total'], $currency);
  }

  /**
   * Whether a line is an amount typed in at the register, not a catalog item.
   */
  protected function isCustomAmount(array $item): bool {
    return $item['item_type'] === 'CUSTOM_AMOUNT';
  }

  /**
   * Names a product variation together with its product.
   */
  protected function variationLabel(ProductVariationInterface $variation): string {
    $product = $variation->getProduct();
    return $product ? $product->label() . ' – ' . $variation->getTitle() : (string) $variation->getTitle();
  }

  /**
   * Notes shown when the sales linked to an order don't add up to its total.
   *
   * @return string
   *   The warning, or an empty string if they agree.
   */
  protected function totalMismatch(array $link, string $currency): string {
    if ($link['sales_total'] === $link['order_total']) {
      return '';
    }
    return (string) $this->t('Totals differ: linked sales @sales, order @order', [
      '@sales' => $this->money($link['sales_total'], $currency),
      '@order' => $this->money($link['order_total'], $currency),
    ]);
  }

  /**
   * Describes how a suggested order relates to its sale.
   */
  protected function suggestionNotes(array $sale): string {
    $days = $sale['match']['days'];
    $notes = [];
    $notes[] = match (TRUE) {
      $days === 0 => $this->t('same day'),
      $days > 0 => $this->formatPlural($days, 'dated 1 day later', 'dated @count days later'),
      default => $this->formatPlural(-$days, 'dated 1 day earlier', 'dated @count days earlier'),
    };
    if (count($sale['match']['sale_ids']) > 1) {
      $notes[] = $this->t('covers @count sales', ['@count' => count($sale['match']['sale_ids'])]);
    }
    if ($sale['cash'] || $sale['other']) {
      $notes[] = $this->t('card portion only');
    }
    return implode(', ', $notes);
  }

  /**
   * Labels for each sale status.
   */
  protected function statusLabels(): array {
    return [
      SalesReport::MISSING => $this->t('Card, not found in Drupal'),
      SalesReport::MATCHED => $this->t('Card, suggested match'),
      SalesReport::CASH => $this->t('Cash'),
      SalesReport::OTHER => $this->t('Other payment types'),
      SalesReport::LINKED => $this->t('Linked'),
    ];
  }

  /**
   * Builds a link to a Drupal order.
   */
  protected function orderLink(OrderInterface $order): array {
    $title = $this->t('Order @number', ['@number' => $order->getOrderNumber() ?: $order->id()]);
    return Link::fromTextAndUrl($title, $order->toUrl())->toRenderable();
  }

  /**
   * Formats an amount in cents.
   */
  protected function money(int $cents, string $currency): string {
    return $this->currencyFormatter->format(sprintf('%.2f', $cents / 100), $currency);
  }

}
