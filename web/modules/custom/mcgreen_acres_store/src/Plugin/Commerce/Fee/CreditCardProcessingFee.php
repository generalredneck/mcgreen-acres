<?php

namespace Drupal\mcgreen_acres_store\Plugin\Commerce\Fee;

use Drupal\commerce_fee\Attribute\CommerceFee;
use Drupal\commerce_fee\Entity\FeeInterface;
use Drupal\commerce_fee\Plugin\Commerce\Fee\OrderFeeBase;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Calculates the Stripe processing fee (2.9% + $0.30) and adds it to the order.
 *
 * Uses a second pass to account for Stripe also taking fees from the fee
 * itself.Skipped when the order's field_cover_stripe_fees is explicitly set to
 * FALSE.
 *
 * Presented to customers as a voluntary "Support the Farm" contribution
 * rather than a credit card surcharge, since Texas (Tex. Bus. & Com. Code
 * §604A.002) prohibits merchants from imposing a surcharge for paying by
 * credit card.
 */
#[CommerceFee(
  id: 'credit_card_processing_fee',
  label: new TranslatableMarkup("Cover farmer's processing fees"),
  entity_type: 'commerce_order',
)]
class CreditCardProcessingFee extends OrderFeeBase {

  /**
   * {@inheritdoc}
   */
  public function apply(EntityInterface $entity, FeeInterface $fee): void {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;

    // NULL (not yet set) is treated as opted-in. Only skip on explicit FALSE.
    // Uses a loose falsy check because the in-memory value may be a PHP
    // boolean (just set via $order->set()) or an int/string 0 (loaded from
    // storage) — a strict `=== 0` comparison misses the boolean FALSE case.
    if ($order->hasField('field_cover_stripe_fees')) {
      $cover_fees = $order->get('field_cover_stripe_fees')->value;
      if ($cover_fees !== NULL && !$cover_fees) {
        return;
      }
    }

    $total = $order->getTotalPrice();
    if (!$total || $total->isZero()) {
      return;
    }

    $fee_amount = static::calculateFee((float) $total->getNumber());

    $order->addAdjustment(new Adjustment([
      'type' => 'fee',
      'label' => $fee->getDisplayName() ?: (string) new TranslatableMarkup("Cover Farmer's processing fees"),
      'amount' => new Price((string) $fee_amount, $total->getCurrencyCode()),
      'source_id' => $fee->id(),
    ]));
  }

  /**
   * Returns the order subtotal with any existing processing fee excluded.
   *
   * Used to compute the fee amount shown to the customer before it's added,
   * without letting the fee compound on itself if a form rebuilds.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\commerce_price\Price
   *   The subtotal before the processing fee.
   */
  public static function getSubtotalExcludingFee(OrderInterface $order): Price {
    $total = $order->getTotalPrice();
    foreach ($order->getAdjustments(['fee']) as $adjustment) {
      $total = $total->subtract($adjustment->getAmount());
    }
    return $total;
  }

  /**
   * Calculates the fee amount needed to cover Stripe's 2.9% + $0.30 charge.
   *
   * Two passes: the second accounts for Stripe taking fees from the fee itself.
   *
   * @param float $order_total
   *   The order total in dollars, before the fee is added.
   *
   * @return float
   *   The fee amount to add, rounded to 2 decimal places.
   */
  public static function calculateFee(float $order_total): float {
    $first_pass = ($order_total * 0.029) + 0.30;
    $fee = round((($order_total + $first_pass) * 0.029) + 0.30, 2);
    return $fee;
  }

}
