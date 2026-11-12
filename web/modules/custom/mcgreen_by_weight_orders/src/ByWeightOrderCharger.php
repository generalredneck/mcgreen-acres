<?php

namespace Drupal\mcgreen_by_weight_orders;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\OrderRefreshInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Applies packed weights to by-weight items and captures the exact amount.
 */
class ByWeightOrderCharger {

  /**
   * Adjustment source ID used to find and replace our own adjustments.
   */
  const SOURCE_ID = 'by_weight';

  /**
   * Order item type for items sold by weight.
   */
  const ORDER_ITEM_TYPE = 'by_weight';

  /**
   * Payment gateway that holds the card for by-weight orders.
   */
  const GATEWAY_ID = 'stripe_by_weight';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected OrderRefreshInterface $orderRefresh,
    protected RounderInterface $rounder,
    protected CurrencyFormatterInterface $currencyFormatter,
  ) {}

  /**
   * Returns the by-weight order items of an order.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface[]
   *   The order items, keyed by ID.
   */
  public function getByWeightItems(OrderInterface $order): array {
    $items = [];
    foreach ($order->getItems() as $item) {
      if ($item->bundle() === self::ORDER_ITEM_TYPE) {
        $items[$item->id()] = $item;
      }
    }
    return $items;
  }

  /**
   * Returns the authorized, not-yet-captured payments of an order.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface[]
   *   The payments.
   */
  public function getAuthorizedPayments(OrderInterface $order): array {
    /** @var \Drupal\commerce_payment\PaymentStorageInterface $payment_storage */
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payments = $payment_storage->loadMultipleByOrder($order);
    return array_filter($payments, fn (PaymentInterface $payment) => $payment->getState()->getId() === 'authorization');
  }

  /**
   * Returns the most a line can cost: the amount authorized for it.
   *
   * Once a line is weighed its unit price becomes the actual price, so the
   * authorized unit price is remembered in the item data the first time.
   */
  public function getAuthorizedPrice(OrderItemInterface $item) {
    $data = $item->getData(self::SOURCE_ID, []);
    if (!empty($data['authorized_unit_price'])) {
      $unit = new Price($data['authorized_unit_price']['number'], $data['authorized_unit_price']['currency_code']);
    }
    else {
      $unit = $item->getUnitPrice();
    }
    return $unit->multiply($item->getQuantity());
  }

  /**
   * Returns the line title without the packed weight suffix.
   */
  public function getBaseTitle(OrderItemInterface $item): string {
    $data = $item->getData(self::SOURCE_ID, []);
    return $data['base_title'] ?? $item->getTitle();
  }

  /**
   * Returns the rate per lb of the item's variation.
   */
  protected function getRate(OrderItemInterface $item): Price {
    $variation = $item->getPurchasedEntity();
    if (!$variation || !$variation->hasField('field_rate_per_lb') || $variation->get('field_rate_per_lb')->isEmpty()) {
      throw new \InvalidArgumentException(sprintf('"%s" has no rate per lb.', $item->label()));
    }
    /** @var \Drupal\commerce_price\Plugin\Field\FieldType\PriceItem $rate_item */
    $rate_item = $variation->get('field_rate_per_lb')->first();
    return $rate_item->toPrice();
  }

  /**
   * Returns the price a package should cost at the given weight.
   */
  public function getPriceForWeight(OrderItemInterface $item, string $weight) {
    return $this->rounder->round($this->getRate($item)->multiply($weight));
  }

  /**
   * Prices a line at its actual weight, in memory.
   *
   * Every receipt, order page and total then shows the real price of each
   * package instead of one combined adjustment. The title carries the weight
   * and rate, so the line can be checked by hand.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $item
   *   The order item.
   * @param string $weight
   *   Packed weight in lb (total for the line).
   * @param \Drupal\commerce_price\Price $line_price
   *   The price of the whole line at that weight.
   */
  protected function repriceItem(OrderItemInterface $item, string $weight, Price $line_price): void {
    // Remember what was authorized and the original title before the line is
    // repriced, so the line can be weighed again and still be capped.
    $data = $item->getData(self::SOURCE_ID, []);
    if (empty($data['authorized_unit_price'])) {
      $data['authorized_unit_price'] = $item->getUnitPrice()->toArray();
      $data['base_title'] = $item->getTitle();
    }
    $item->setData(self::SOURCE_ID, $data);
    // Earlier versions recorded the weight as an order-level adjustment.
    foreach ($item->getAdjustments() as $adjustment) {
      if ($adjustment->getSourceId() === self::SOURCE_ID) {
        $item->removeAdjustment($adjustment);
      }
    }
    $rate = $this->getRate($item);
    $item->set('field_packed_weight', $weight);
    $item->setTitle(sprintf('%s (%s lb @ %s/lb)', $data['base_title'], $weight, $this->currencyFormatter->format($rate->getNumber(), $rate->getCurrencyCode())), TRUE);
    $item->setUnitPrice($line_price->divide((string) $item->getQuantity()), TRUE);
  }

  /**
   * Converts weight adjustments from earlier versions into line prices.
   *
   * The order total is left exactly as it was: every line is checked before
   * saving, and the order itself is only saved when the total is unchanged.
   *
   * @return bool
   *   TRUE when the order was converted, FALSE when it had nothing to convert.
   *
   * @throws \RuntimeException
   *   When the total would change.
   */
  public function convertLegacyAdjustments(OrderInterface $order): bool {
    $converted = [];
    $before = $order->getTotalPrice();
    foreach ($this->getByWeightItems($order) as $item) {
      $legacy = array_filter($item->getAdjustments(), fn ($adjustment) => $adjustment->getSourceId() === self::SOURCE_ID);
      if (!$legacy || $item->get('field_packed_weight')->isEmpty()) {
        continue;
      }
      $line_price = $item->getUnitPrice()->multiply($item->getQuantity());
      foreach ($legacy as $adjustment) {
        $line_price = $line_price->add($adjustment->getAmount());
      }
      $this->repriceItem($item, (string) $item->get('field_packed_weight')->value, $line_price);
      // The new unit price times the quantity must give back the same line
      // price, otherwise rounding would change the order total.
      $new_line = $this->rounder->round($item->getUnitPrice()->multiply($item->getQuantity()));
      if (!$new_line->equals($this->rounder->round($line_price))) {
        throw new \RuntimeException(sprintf('Order %s line %s would change from %s to %s.', $order->id(), $item->id(), $line_price, $new_line));
      }
      $converted[] = $item;
    }
    if (!$converted) {
      return FALSE;
    }
    foreach ($converted as $item) {
      $item->save();
    }
    $order->recalculateTotalPrice();
    if (!$order->getTotalPrice()->equals($before)) {
      throw new \RuntimeException(sprintf('Order %s total changed from %s to %s.', $order->id(), $before, $order->getTotalPrice()));
    }
    $order->save();
    return TRUE;
  }

  /**
   * Applies weights to the by-weight items and optionally captures the payment.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param array $weights
   *   Packed weights in lb (total for the line), keyed by order item ID.
   * @param bool $capture
   *   Whether to capture the authorization at the new total.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The captured amount, or NULL when nothing was captured.
   *
   * @throws \InvalidArgumentException
   *   When a weight would cost more than the amount authorized.
   */
  public function apply(OrderInterface $order, array $weights, bool $capture = TRUE) {
    $items = $this->getByWeightItems($order);
    // Check the payment can be captured before saving anything.
    $payments = $capture ? $this->getAuthorizedPayments($order) : [];
    $payment = reset($payments) ?: NULL;
    $gateway = NULL;
    if ($payment) {
      $gateway = $payment->getPaymentGateway()->getPlugin();
      if (!$gateway instanceof SupportsAuthorizationsInterface) {
        throw new \InvalidArgumentException(sprintf('The payment gateway "%s" cannot capture an authorization.', $payment->getPaymentGateway()->label()));
      }
    }
    // Validate every line before saving anything.
    $actuals = [];
    foreach ($items as $id => $item) {
      if (!isset($weights[$id]) || (float) $weights[$id] <= 0) {
        throw new \InvalidArgumentException(sprintf('Enter a packed weight for "%s".', $this->getBaseTitle($item)));
      }
      $original = $this->getAuthorizedPrice($item);
      $actuals[$id] = $this->getPriceForWeight($item, (string) $weights[$id]);
      if ($actuals[$id]->greaterThan($original)) {
        throw new \InvalidArgumentException(sprintf('%s lb of "%s" would cost %s, more than the %s authorized for it. Pick a lighter package.', $weights[$id], $this->getBaseTitle($item), $actuals[$id], $original));
      }
    }
    foreach ($items as $id => $item) {
      $this->repriceItem($item, (string) $weights[$id], $actuals[$id]);
      // Items are saved here, not after the refresh: saving them later did not
      // persist the new weight.
      $item->save();
    }

    // The refresh below re-reads catalog prices. Pin the other lines of a mixed
    // order at what the customer was shown, so only the weighed lines change.
    foreach ($order->getItems() as $item) {
      if ($item->bundle() !== self::ORDER_ITEM_TYPE) {
        $item->setTitle($item->getTitle(), TRUE);
        $item->setUnitPrice($item->getUnitPrice(), TRUE);
      }
    }

    // Recalculate in memory first so nothing is saved if the total is wrong.
    $this->orderRefresh->refresh($order);
    $order->recalculateTotalPrice();
    $balance = $order->getBalance();
    if ($payment && $balance->greaterThan($payment->getAmount())) {
      throw new \InvalidArgumentException(sprintf('The new order balance %s is more than the %s authorized.', $balance, $payment->getAmount()));
    }

    $order->save();

    if (!$payment || !$gateway) {
      return NULL;
    }
    $gateway->capturePayment($payment, $balance);
    return $balance;
  }

}
