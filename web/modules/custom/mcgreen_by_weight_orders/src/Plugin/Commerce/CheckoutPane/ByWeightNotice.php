<?php

namespace Drupal\mcgreen_by_weight_orders\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcgreen_by_weight_orders\ByWeightOrderCharger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tells the customer their card is held now and charged once we weigh it.
 *
 * Only visible when the order has a by-weight item. Orders with one are
 * authorized at checkout (stripe_by_weight) and captured later at the weighed
 * amount, so the amount the customer sees now is a ceiling, not the charge.
 */
#[CommerceCheckoutPane(
  id: 'by_weight_notice',
  label: new TranslatableMarkup('Sold-by-weight notice'),
  default_step: '_disabled',
  wrapper_element: 'container',
)]
class ByWeightNotice extends CheckoutPaneBase {

  /**
   * The currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->currencyFormatter = $container->get('commerce_price.currency_formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible(): bool {
    foreach ($this->order->getItems() as $item) {
      if ($item->bundle() === ByWeightOrderCharger::ORDER_ITEM_TYPE) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {
    $total = $this->order->getTotalPrice();
    $amount = $total ? $this->currencyFormatter->format($total->getNumber(), $total->getCurrencyCode()) : '';
    $pane_form['notice'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['alert', 'alert-info']],
      'content' => [
        '#markup' => $this->t('<strong>Your order contains by-weight items. A charge on your card will be held for @amount.</strong> When we pull your order, we will adjust the charge based on the actual weight. You will not be charged more than @amount for this order. Any unused part of the hold is released; how soon depends on your bank.', ['@amount' => $amount]),
      ],
    ];
    return $pane_form;
  }

}
