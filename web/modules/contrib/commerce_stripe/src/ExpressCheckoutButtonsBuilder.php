<?php

namespace Drupal\commerce_stripe;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripePaymentElementInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Provides a helper for building the Express Checkout payment buttons.
 */
class ExpressCheckoutButtonsBuilder implements ExpressCheckoutButtonsBuilderInterface {

  use StringTranslationTrait;

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * Constructs a new ExpressCheckoutButtonsBuilder object.
   *
   * @param \Drupal\commerce_price\MinorUnitsConverterInterface $minorUnitsConverter
   *   The minor units converter.
   * @param \Drupal\commerce_stripe\ExpressCheckoutHelperInterface $expressCheckoutHelper
   *   The Express Checkout helper service.
   */
  public function __construct(
    protected MinorUnitsConverterInterface $minorUnitsConverter,
    protected ExpressCheckoutHelperInterface $expressCheckoutHelper,
  ) {}

  /**
   * Set the shipping order manager.
   *
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shipping_order_manager
   *   The shipping order manager.
   */
  public function setShippingOrderManager(ShippingOrderManagerInterface $shipping_order_manager) {
    $this->shippingOrderManager = $shipping_order_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function build(OrderInterface $order, PaymentGatewayInterface $payment_gateway): array {
    $element = [];

    if (!$payment_gateway->getPlugin() instanceof StripePaymentElementInterface) {
      return $element;
    }
    /** @var \Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripePaymentElementInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    $express_checkout_config = $payment_gateway_plugin->getExpressCheckout();

    $route_parameters = [
      'commerce_order' => $order->id(),
      'commerce_payment_gateway' => $payment_gateway->id(),
    ];
    // To display validation errors.
    $element['payment_errors'] = [
      '#type' => 'markup',
      '#markup' => '<div id="payment-errors"></div>',
      '#weight' => -200,
    ];
    $element['#attributes']['class'][] = 'stripe-express-checkout-element-form';
    $element_id = Html::getUniqueId('stripe-express-checkout-element');
    $element['#attached']['library'][] = 'commerce_stripe/express_checkout_element';
    $element['#attached']['drupalSettings']['commerceStripeExpressCheckoutElement'] = [];
    $js_settings = &$element['#attached']['drupalSettings']['commerceStripeExpressCheckoutElement'];
    $js_settings = [
      'publishableKey' => $payment_gateway_plugin->getPublishableKey(),
      'elementId' => $element_id,
      'isShippable' => $this->isShippableOrder($order),
      'confirmPaymentUrl' => Url::fromRoute('commerce_stripe.express_checkout.payment_confirm', $route_parameters)->toString(),
      'shippingAddressChangeUrl' => Url::fromRoute('commerce_stripe.express_checkout.shipping_address_change', ['commerce_order' => $order->id()])->toString(),
      'shippingRateChangeUrl' => Url::fromRoute('commerce_stripe.express_checkout.shipping_rate_change', ['commerce_order' => $order->id()])->toString(),
      'cancelUrl' => Url::fromRoute('commerce_stripe.express_checkout.cancel', ['commerce_order' => $order->id()])->toString(),
      'validateShippingAddressUrl' => Url::fromRoute('commerce_stripe.express_checkout.validate_shipping_address', ['commerce_order' => $order->id()])->toString(),
      'createElementsOptions' => [
        'mode' => 'payment',
        'amount' => $this->minorUnitsConverter->toMinorUnits($order->getTotalPrice()),
        'currency' => strtolower($order->getTotalPrice()->getCurrencyCode()),
        'captureMethod' => $payment_gateway_plugin->getCaptureMethod(),
      ],
      'expressCheckoutOptions' => [],
      'onClickEventOptions' => [
        'emailRequired' => TRUE,
        'phoneNumberRequired' => (bool) $express_checkout_config['collect_phone_number'],
        'billingAddressRequired' => (bool) $express_checkout_config['collect_billing_address'],
        'lineItems' => $this->expressCheckoutHelper->getOrderLineItems($order),
      ],
    ];
    // Adds payment method usage to JS settings.
    $payment_method_usage = $payment_gateway_plugin->getPaymentMethodUsage();
    if ($payment_method_usage !== 'single_use') {
      $js_settings['createElementsOptions']['setupFutureUsage'] = $payment_gateway_plugin->getPaymentMethodUsage();
    }
    // If all checkboxes in "allowed_payment_method_types" are unset allow all
    // payment method types, through no passing "paymentMethods":
    if (!empty(array_filter($express_checkout_config['allowed_payment_method_types']))) {
      $payment_method_types = [];
      foreach ($express_checkout_config['allowed_payment_method_types'] as $key => $payment_method_type) {
        $payment_method_types[$key] = !empty($payment_method_type) ? 'auto' : 'never';
      }
      $js_settings['expressCheckoutOptions']['paymentMethods'] = $payment_method_types;
    }
    // Adds button themes and types to JS settings.
    if (!empty($express_checkout_config['button_styles'])) {
      foreach ($express_checkout_config['button_styles'] as $key => $button_style) {
        $js_settings['expressCheckoutOptions']['buttonTheme'][$key] = $button_style['theme'];
        $js_settings['expressCheckoutOptions']['buttonType'][$key] = $button_style['type'];
      }
    }
    // Adds shipping settings to JS settings.
    if ($shipping_configs = $this->getShippingConfiguration($order)) {
      $js_settings['onClickEventOptions'] = array_merge($js_settings['onClickEventOptions'], $shipping_configs);
    }
    $element['stripe_express_checkout_element'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'id' => $element_id,
      ],
    ];

    return $element;
  }

  /**
   * Gets the shipping configuration for the order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return array
   *   Shipping configuration for the order.
   */
  protected function getShippingConfiguration(OrderInterface $order): array {
    $shipping_settings = [];

    if (!$this->isShippableOrder($order)) {
      return $shipping_settings;
    }

    $store = $order->getStore();
    $available_countries = [];
    foreach ($store->get('shipping_countries') as $country_item) {
      $available_countries[] = $country_item->value;
    }

    return [
      'shippingAddressRequired' => TRUE,
      'allowedShippingCountries' => $available_countries,
      'shippingRates' => [
        [
          'id' => 'no-shipping-address',
          'displayName' => $this->t('Enter a shipping address to see options'),
          'amount' => 0,
        ],
      ],
    ];
  }

  /**
   * Whether the order is shippable.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return bool
   *   Whether the order is shippable.
   */
  public function isShippableOrder(OrderInterface $order): bool {
    return ($this->shippingOrderManager?->isShippable($order) ?? FALSE);
  }

}
