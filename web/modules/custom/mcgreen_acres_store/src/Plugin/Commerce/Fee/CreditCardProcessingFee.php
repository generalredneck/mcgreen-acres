<?php

namespace Drupal\mcgreen_acres_store\Plugin\Commerce\Fee;

use Drupal\commerce_fee\Attribute\CommerceFee;
use Drupal\commerce_fee\Entity\FeeInterface;
use Drupal\commerce_fee\Plugin\Commerce\Fee\OrderFeeBase;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Calculates the Stripe processing fee (2.9% + $0.30) and adds it to the order.
 *
 * Uses a second pass to account for Stripe also taking fees from the fee itself.
 * Skipped when the order's field_cover_stripe_fees is explicitly set to FALSE.
 */
#[CommerceFee(
  id: 'credit_card_processing_fee',
  label: new TranslatableMarkup('Credit card processing fee (2.9% + $0.30)'),
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
    if ($order->hasField('field_cover_stripe_fees')
      && $order->get('field_cover_stripe_fees')->value === 0) {
      return;
    }

    $total = $order->getTotalPrice();
    if (!$total || $total->isZero()) {
      return;
    }

    $fee_amount = static::calculateFee((float) $total->getNumber());

    $order->addAdjustment(new Adjustment([
      'type' => 'fee',
      'label' => $fee->getDisplayName() ?: (string) new TranslatableMarkup('Credit Card Processing Fee'),
      'amount' => new Price((string) $fee_amount, $total->getCurrencyCode()),
      'source_id' => $fee->id(),
    ]));
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
    return max($fee, 0.50);
  }

}
