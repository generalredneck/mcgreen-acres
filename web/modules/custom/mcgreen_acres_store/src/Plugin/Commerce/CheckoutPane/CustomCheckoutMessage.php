<?php

namespace Drupal\mcgreen_acres_store\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\commerce_custom_checkout_message\Plugin\Commerce\CheckoutPane\CustomCheckoutMessage as CheckoutPaneCustomCheckoutMessage;

/**
 * Overrides the checkout sidebar message to reflect actual fulfillment.
 *
 * The configured message (in the checkout flow admin UI) describes the
 * appointment-pickup process and is shown throughout checkout for any
 * order that still needs fulfillment. A cart made entirely of farm-stand
 * items is picked up immediately, so the appointment instructions don't
 * apply; this swaps in a short farm-stand-specific message instead.
 */
class CustomCheckoutMessage extends CheckoutPaneCustomCheckoutMessage {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    if (!_mcgreen_acres_store_cart_needs_fulfillment($this->order)) {
      $message = $this->token->replace($this->farmstandMessage(), ['commerce_order' => $this->order]);
      $pane_form['message'] = [
        '#markup' => Markup::create($message),
      ];
      return $pane_form;
    }

    return parent::buildPaneForm($pane_form, $form_state, $complete_form);
  }

  /**
   * Builds the message shown when the order will be picked up immediately.
   */
  protected function farmstandMessage(): string {
    return '<div class="alert alert-success"><p class="lead"><strong>'
      . $this->t("You're all set — pick this up now at the Farm Stand.")
      . '</strong></p><p>' . $this->t('We are located at:')
      . '<br><a href="https://maps.app.goo.gl/g27qR5a6KtSQ9XrS7" target="_blank" rel="noopener noreferrer">2414 Westmoreland Rd Red Oak, Texas 75154</a></p></div>';
  }

}
