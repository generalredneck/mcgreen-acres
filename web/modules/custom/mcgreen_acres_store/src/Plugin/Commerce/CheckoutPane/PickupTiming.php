<?php

namespace Drupal\mcgreen_acres_store\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Asks a farm-stand-eligible customer when they're picking up their order.
 *
 * If the cart contains anything that isn't available at the farm stand,
 * fulfillment already has to wait on an appointment - there's nothing to
 * ask. But a cart made entirely of farm-stand items is genuinely ambiguous:
 * it can be bought by someone standing at the stand right now, or by
 * someone ordering the same items ahead of time (e.g. holding a
 * small-quantity item for later pickup). Composition alone can't tell
 * those apart, so this asks directly and stores the answer in
 * field_needs_fulfillment - the same field staff use to override
 * fulfillment on manually-entered orders (see OrderNeedsFulfillmentForm).
 */
#[CommerceCheckoutPane(
  id: "pickup_timing",
  label: new TranslatableMarkup("Pickup timing"),
  admin_description: new TranslatableMarkup("Asks farm-stand-eligible customers whether they're picking up today or ordering ahead."),
  default_step: "_disabled",
  wrapper_element: "fieldset",
)]
class PickupTiming extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    return !_mcgreen_acres_store_cart_needs_fulfillment($this->order);
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $needs_fulfillment = $this->order->get('field_needs_fulfillment')->value;
    $default = ((bool) $needs_fulfillment) ? 'ahead' : 'today';

    $pane_form['timing'] = [
      '#type' => 'radios',
      '#title' => $this->t('When are you picking this up?'),
      '#options' => [
        'today' => $this->t("I'm at the Farm Stand with my items in hand"),
        'ahead' => $this->t("I'm ordering ahead or need to schedule a pickup"),
      ],
      '#default_value' => $default,
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($this->getPluginId());
    $this->order->set('field_needs_fulfillment', ($values['timing'] ?? 'today') === 'ahead');
  }

}
