<?php

namespace Drupal\mcgreen_acres_store\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CompletionMessage as CheckoutPaneCompletionMessage;

/**
 * Overrides the completion message to reflect actual fulfillment.
 *
 * The configured message (in the checkout flow admin UI) describes the
 * appointment-pickup process and is shown as-is for any order that still
 * needs fulfillment. Once the PickupTiming pane has resolved whether this
 * order is being picked up immediately, showing appointment instructions
 * to someone who already confirmed they're at the stand would be actively
 * wrong; this swaps in a short farm-stand-specific message instead.
 */
class CompletionMessage extends CheckoutPaneCompletionMessage {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form = parent::buildPaneForm($pane_form, $form_state, $complete_form);

    if (!_mcgreen_acres_store_order_needs_fulfillment($this->order)) {
      $message = $this->token->replace($this->farmstandMessage(), ['commerce_order' => $this->order]);
      $pane_form['message']['#message'] = [
        '#type' => 'processed_text',
        '#text' => $message,
        '#format' => 'full_html',
      ];
    }

    return $pane_form;
  }

  /**
   * Builds the message shown once immediate pickup is confirmed.
   */
  protected function farmstandMessage(): string {
    return '<h2>' . $this->t('Your order is complete!') . '</h2>'
      . '<div class="alert alert-success"><p class="lead"><strong>'
      . $this->t('Feel free to take your items from the Farm Stand!')
      . '</strong></p></div>'
      . '<p>' . $this->t('Your order number is [commerce_order:order_number].') . '</p>'
      . '<p>' . $this->t('An email has been sent with your receipt.') . '</p>'
      . '<p>' . $this->t('An account has been created for you. Check your email for a link to set a password so you can review your orders and manage any subscriptions.') . '</p>';
  }

}
