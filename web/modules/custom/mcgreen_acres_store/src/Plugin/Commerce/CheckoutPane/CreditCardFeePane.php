<?php

namespace Drupal\mcgreen_acres_store\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcgreen_acres_store\Plugin\Commerce\Fee\CreditCardProcessingFee;

/**
 * Provides an opt-out checkbox for the "Support the Farm" processing fee.
 *
 * The fee is applied automatically by FeeOrderProcessor. This pane lets the
 * customer remove it during regular checkout. Express Checkout skips this
 * step (it jumps straight to the review step), so those orders rely on the
 * cart-page checkbox in mcgreen_acres_store.module to capture the opt-out
 * before checkout starts; field_cover_stripe_fees defaults to opted-in.
 */
#[CommerceCheckoutPane(
  id: 'credit_card_fee',
  label: new TranslatableMarkup("Cover farmer's processing fees"),
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
    $subtotal = CreditCardProcessingFee::getSubtotalExcludingFee($this->order);
    $fee_amount = CreditCardProcessingFee::calculateFee((float) $subtotal->getNumber());
    $formatted = '$' . number_format($fee_amount, 2);

    $pane_form['cover_fees'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Cover farmer's processing fees (+@amount)", ['@amount' => $formatted]),
      '#description' => $this->t('100% optional. This voluntary contribution helps offset the cost of accepting card payments, so more of your order goes directly to the farm.'),
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

}
