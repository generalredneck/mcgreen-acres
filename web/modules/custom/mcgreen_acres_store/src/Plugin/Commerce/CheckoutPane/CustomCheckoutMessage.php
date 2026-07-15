<?php

namespace Drupal\mcgreen_acres_store\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\commerce_custom_checkout_message\Plugin\Commerce\CheckoutPane\CustomCheckoutMessage as CheckoutPaneCustomCheckoutMessage;

/**
 * Overrides the checkout sidebar message to reflect actual fulfillment.
 *
 * This pane now lives on the review step only (placed after
 * PickupTiming's step, so field_needs_fulfillment is always explicitly
 * answered by the time it renders). There's nothing to tell someone
 * picking up at the Farm Stand before they've even paid, so this shows
 * nothing for that case; the configured message (appointment
 * instructions, pointing them at Comments & Special Requests) still shows
 * as-is for anyone who needs fulfillment.
 */
class CustomCheckoutMessage extends CheckoutPaneCustomCheckoutMessage {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    if (!_mcgreen_acres_store_order_needs_fulfillment($this->order)) {
      return $pane_form;
    }

    return parent::buildPaneForm($pane_form, $form_state, $complete_form);
  }

}
