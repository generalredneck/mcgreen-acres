<?php

namespace Drupal\mcgreen_by_weight_orders;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\OrderRefreshInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
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

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected OrderRefreshInterface $orderRefresh,
    protected RounderInterface $rounder,
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
    $payments = $this->entityTypeManager->getStorage('commerce_payment')->loadMultipleByOrder($order);
    return array_filter($payments, fn (PaymentInterface $payment) => $payment->getState()->getId() === 'authorization');
  }

  /**
   * Returns the price a package should cost at the given weight.
   */
  public function getPriceForWeight(OrderItemInterface $item, string $weight) {
    $variation = $item->getPurchasedEntity();
    if (!$variation || !$variation->hasField('field_rate_per_lb') || $variation->get('field_rate_per_lb')->isEmpty()) {
      throw new \InvalidArgumentException(sprintf('"%s" has no rate per lb.', $item->label()));
    }
    return $this->rounder->round($variation->get('field_rate_per_lb')->first()->toPrice()->multiply($weight));
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
    // Validate every line before saving anything.
    $actuals = [];
    foreach ($items as $id => $item) {
      if (!isset($weights[$id]) || (float) $weights[$id] <= 0) {
        throw new \InvalidArgumentException(sprintf('Enter a packed weight for "%s".', $item->label()));
      }
      $original = $item->getUnitPrice()->multiply($item->getQuantity());
      $actuals[$id] = $this->getPriceForWeight($item, (string) $weights[$id]);
      if ($actuals[$id]->greaterThan($original)) {
        throw new \InvalidArgumentException(sprintf('%s lb of "%s" would cost %s, more than the %s authorized for it. Pick a lighter package.', $weights[$id], $item->label(), $actuals[$id], $original));
      }
    }
    foreach ($items as $id => $item) {
      $weight = (string) $weights[$id];
      $original = $item->getUnitPrice()->multiply($item->getQuantity());
      // Replace any earlier weight adjustment on this line.
      foreach ($item->getAdjustments() as $adjustment) {
        if ($adjustment->getSourceId() === self::SOURCE_ID) {
          $item->removeAdjustment($adjustment);
        }
      }
      $item->set('field_packed_weight', $weight);
      $item->addAdjustment(new Adjustment([
        'type' => 'custom',
        'label' => sprintf('Weight adjustment (%s lb packed)', $weight),
        'amount' => $actuals[$id]->subtract($original),
        'source_id' => self::SOURCE_ID,
        'locked' => TRUE,
      ]));
      // Items are saved here, not after the refresh: saving them later did not
      // persist the new weight.
      $item->save();
    }

    // Recalculate in memory first so nothing is saved if the total is wrong.
    $this->orderRefresh->refresh($order);
    $order->recalculateTotalPrice();
    $payments = $capture ? $this->getAuthorizedPayments($order) : [];
    $balance = $order->getBalance();
    $payment = reset($payments);
    if ($payment && $balance->greaterThan($payment->getAmount())) {
      throw new \InvalidArgumentException(sprintf('The new order balance %s is more than the %s authorized.', $balance, $payment->getAmount()));
    }

    $order->save();

    if (!$payment) {
      return NULL;
    }
    $payment->getPaymentGateway()->getPlugin()->capturePayment($payment, $balance);
    return $balance;
  }

}
