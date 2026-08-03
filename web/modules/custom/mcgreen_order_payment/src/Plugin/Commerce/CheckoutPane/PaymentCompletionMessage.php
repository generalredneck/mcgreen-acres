<?php

namespace Drupal\mcgreen_order_payment\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Completion message for the "Pay Order" flow.
 *
 * The mcgreen_acres_store module overrides the core "completion_message"
 * pane ID sitewide to show farm-stand-vs-appointment pickup text based on
 * the order's fulfillment status. That logic doesn't apply here — this flow
 * is for paying an already-built order, not fulfillment pickup — so this
 * uses its own plugin ID to stay isolated from it.
 */
#[CommerceCheckoutPane(
  id: "mcgreen_order_payment_completion_message",
  label: new TranslatableMarkup("Payment completion message"),
  default_step: "complete",
  wrapper_element: "container",
)]
class PaymentCompletionMessage extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * The token service.
   */
  protected Token $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager')
    );
    $instance->token = $container->get('token');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $message = '<h2>' . $this->t('Payment received!') . '</h2><p>' . $this->t('Thank you, your payment for order [commerce_order:order_number] has been received.') . '</p>';
    $pane_form['message'] = [
      '#type' => 'processed_text',
      '#text' => $this->token->replace($message, ['commerce_order' => $this->order]),
      '#format' => 'full_html',
    ];
    return $pane_form;
  }

}
