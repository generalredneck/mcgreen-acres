<?php

namespace Drupal\mcgreen_square_sync\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Link;
use Drupal\mcgreen_square_sync\ItemResolver;
use Drupal\mcgreen_square_sync\SaleImporter;
use Drupal\mcgreen_square_sync\SalesReport;

/**
 * How Square sales are written out on the admin pages.
 *
 * Classes using this need "currencyFormatter" and "entityTypeManager"
 * properties.
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
   * Whether a line is an amount typed in at the register.
   */
  protected function isCustomAmount(array $item): bool {
    return $item['item_type'] === 'CUSTOM_AMOUNT';
  }

  /**
   * Says what a line will be recorded as.
   *
   * @param array $resolved
   *   The result of ItemResolver::resolve().
   * @param array $item
   *   The stored sale item.
   */
  protected function resolutionText(array $resolved, array $item): string {
    return (string) match ($resolved['type']) {
      ItemResolver::VARIATION => $resolved['quantity'] . ' × ' . $this->variationLabel($resolved['variation']),
      ItemResolver::ADJUSTMENT => $this->t('adjustment'),
      ItemResolver::NO_PRODUCT => $this->t('no product'),
      ItemResolver::NO_SIZE => $this->t('no package size for @quantity', ['@quantity' => $this->quantityText($item['quantity'], $item['unit'] ?? NULL)]),
      default => $this->t('not mapped'),
    };
  }

  /**
   * Writes a quantity with its unit, if it has one.
   */
  protected function quantityText(string $quantity, ?string $unit): string {
    return $unit === ItemResolver::POUND ? $quantity . ' lb' : $quantity;
  }

  /**
   * Product variations to choose from, grouped by product.
   *
   * Subscriptions are left out: importing one would start billing a customer.
   *
   * @return array[]
   *   Options keyed by variation ID, in groups keyed by product title.
   */
  protected function variationOptions(?callable $filter = NULL): array {
    $groups = [];
    $variations = $this->entityTypeManager->getStorage('commerce_product_variation')->loadMultiple();
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
    foreach ($variations as $id => $variation) {
      $product = $variation->getProduct();
      if (!$product || ($variation->hasField('billing_schedule') && !$variation->get('billing_schedule')->isEmpty())) {
        continue;
      }
      if ($filter && !$filter($variation)) {
        continue;
      }
      $label = $this->variationLabel($variation);
      if ($price = $variation->getPrice()) {
        $label .= ' (' . $this->currencyFormatter->format($price->getNumber(), $price->getCurrencyCode()) . ')';
      }
      if (!$variation->isPublished() || !$product->isPublished()) {
        $label .= ' ' . $this->t('(unpublished)');
      }
      $groups[(string) $product->label()][$id] = $label;
    }
    ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
    return $groups;
  }

  /**
   * Names a product variation together with its product.
   */
  protected function variationLabel(ProductVariationInterface $variation): string {
    $title = (string) $variation->getTitle();
    $product = (string) $variation->getProduct()?->label();
    return $product === '' || $product === $title ? $title : $product . ' – ' . $title;
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
      SalesReport::IMPORTED => $this->t('Imported'),
      SalesReport::IGNORED => $this->t('Ignored'),
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

  /**
   * The gateways a payment that was not by card can be recorded with.
   *
   * @return string[]
   *   Labels keyed by gateway ID.
   */
  protected function gatewayOptions(): array {
    $gateways = $this->entityTypeManager->getStorage('commerce_payment_gateway')
      ->loadMultiple(SaleImporter::OTHER_GATEWAYS);
    return array_map(fn($gateway) => (string) $gateway->label(), $gateways);
  }

  /**
   * The gateway suggested for a sale's payments that were not by card.
   */
  protected function defaultGateway(array $sale): string {
    // Square's "cash" is cash here; anything else needs a deliberate choice.
    return $sale['other'] ? '' : 'cash';
  }

}
