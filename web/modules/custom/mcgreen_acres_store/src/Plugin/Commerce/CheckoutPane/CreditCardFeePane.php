<?php

namespace Drupal\mcgreen_acres_store\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcgreen_acres_store\Plugin\Commerce\Fee\CreditCardProcessingFee;

/**
 * Provides an opt-out checkbox for the credit card processing fee.
 *
 * The fee is applied automatically by FeeOrderProcessor. This pane lets the
 * customer remove it during regular checkout. Express Checkout always includes
 * the fee since this pane is not shown in that flow.
 */
#[CommerceCheckoutPane(
  id: 'credit_card_fee',
  label: new TranslatableMarkup('Credit Card Processing Fee'),
  default_step: '_disabled',
  wrapper_element: 'container',
)]
class CreditCardFeePane extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function isVisible(): bool {
    // Hide for recurring orders (subscriptions pay their own fees).
    if ($this->order->bundle() !== 'default') {
      return FALSE;
    }
    $total = $this->order->getTotalPrice();
    return $total && !$total->isZero();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {
    $cover_fees = $this->order->get('field_cover_stripe_fees')->value;
    // NULL (never set) defaults to opted-in.
    $checked = ($cover_fees === NULL || (bool) $cover_fees);

    // Calculate the current fee amount for the label.
    $total = $this->order->getTotalPrice();
    $subtotal = $this->getSubtotalExcludingFee($total);
    $fee_amount = CreditCardProcessingFee::calculateFee((float) $subtotal->getNumber());
    $formatted = '$' . number_format($fee_amount, 2);

    $pane_form['cover_fees'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cover credit card processing fees (@amount)', ['@amount' => $formatted]),
      '#description' => $this->t('Checking this box adds the 2.9% + $0.30 Stripe processing fee to your order so the full amount goes to the farm.'),
      '#default_value' => $checked,
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    $values = $form_state->getValue($pane_form['#parents']);
    $this->order->set('field_cover_stripe_fees', (bool) $values['cover_fees']);
  }

  /**
   * Returns the order subtotal with any existing processing fee excluded.
   *
   * This prevents the fee from compounding on itself if the pane rebuilds.
   *
   * @param \Drupal\commerce_price\Price $total
   *   The current order total.
   *
   * @return \Drupal\commerce_price\Price
   *   The subtotal before the processing fee.
   */
  protected function getSubtotalExcludingFee(Price $total): Price {
    foreach ($this->order->getAdjustments(['fee']) as $adjustment) {
      $total = $total->subtract($adjustment->getAmount());
    }
    return $total;
  }

}
