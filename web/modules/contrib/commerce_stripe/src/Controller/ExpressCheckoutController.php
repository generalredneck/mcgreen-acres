<?php

namespace Drupal\commerce_stripe\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\commerce_stripe\ErrorHelper;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripePaymentElementInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\profile\Entity\ProfileInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe Express Checkout controller.
 */
class ExpressCheckoutController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The minor units converter.
   *
   * @var \Drupal\commerce_price\MinorUnitsConverterInterface
   */
  protected $minorUnitsConverter;

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * The subdivision repository.
   *
   * @var \CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface
   */
  protected $subdivisionRepository;

  /**
   * The shipment manager service.
   *
   * @var \Drupal\commerce_shipping\ShipmentManagerInterface
   */
  protected $shipmentManager;

  /**
   * The Express Checkout helper service.
   *
   * @var \Drupal\commerce_stripe\ExpressCheckoutHelperInterface
   */
  protected $expressCheckoutHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ExpressCheckoutController {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->minorUnitsConverter = $container->get('commerce_price.minor_units_converter');
    $instance->subdivisionRepository = $container->get('address.subdivision_repository');
    $instance->expressCheckoutHelper = $container->get('commerce_stripe.express_checkout_helper');
    if ($container->has('commerce_shipping.shipment_manager')) {
      $instance->shipmentManager = $container->get('commerce_shipping.shipment_manager');
    }
    if ($container->has('commerce_shipping.order_manager')) {
      $instance->shippingOrderManager = $container->get('commerce_shipping.order_manager');
    }

    return $instance;
  }

  /**
   * Reacts on payment confirm event to create a payment intent in Stripe.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $commerce_payment_gateway
   *   The payment gateway.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response.
   */
  public function onPaymentConfirm(OrderInterface $commerce_order, PaymentGatewayInterface $commerce_payment_gateway): Response {
    if (!$commerce_payment_gateway->getPlugin() instanceof StripePaymentElementInterface) {
      throw new AccessException('Invalid payment gateway provided.');
    }

    /** @var \Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripePaymentElementInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $commerce_payment_gateway->getPlugin();

    // Clear the order values set during the regular checkout process
    // when customer first load the payment interface.
    if (!$commerce_order->getData('stripe_express_checkout', FALSE)) {
      // Sets the indication that the customer is using Stripe Express Checkout
      // to complete the order.
      $commerce_order->setData('stripe_express_checkout', TRUE);
      $this->clearOrderCheckoutData($commerce_order);
    }

    // Create a payment intent for an order.
    $intent = $payment_gateway_plugin->createPaymentIntent($commerce_order);
    $commerce_order->set('payment_gateway', $commerce_payment_gateway);
    // Set the checkout_step to review to redirect to the complete step after
    // confirming of payment intent.
    $commerce_order->set('checkout_step', 'review');
    $commerce_order->save();

    return new JsonResponse([
      'clientSecret' => $intent->client_secret,
      'returnUrl' => Url::fromRoute('commerce_payment.checkout.return', [
        'commerce_order' => $commerce_order->id(),
        'step' => 'review',
      ], ['absolute' => TRUE])->toString(),
    ]);
  }

  /**
   * Reacts on shipping address change event.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response.
   */
  public function onShippingAddressChange(OrderInterface $commerce_order, Request $request): Response {
    $body = Json::decode($request->getContent());
    if (!isset($body['shippingAddress']) || !isset($body['name'])) {
      return new Response('Missing shipping address and / or name', 500);
    }
    $shipping_address = $body['shippingAddress'];
    $name = $body['name'];

    // If the order already has a shipment, just update the address
    // otherwise create a new shipment.
    if ($this->shippingOrderManager->hasShipments($commerce_order)) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
      $shipments = $commerce_order->get('shipments')->referencedEntities();
      $shipment = reset($shipments);
    }
    else {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      $shipment = $this->entityTypeManager->getStorage('commerce_shipment')->create([
        'shipping_service' => 'default',
        'order_id' => $commerce_order->id(),
        'type' => $this->getShipmentType($commerce_order),
        'title' => $this->t('Shipment #1'),
      ]);
    }

    // Update the shipping profile with a new address.
    $shipping_profile = $this->getShippingProfile($commerce_order, $shipping_address, $name);
    // Update order shipment.
    $shipment->setShippingProfile($shipping_profile);
    $shipping_methods = $this->getShippingMethodsForShipment($shipment);
    if (!empty($shipping_methods)) {
      $first_shipping_method = reset($shipping_methods);
      $shipment->setShippingMethod($first_shipping_method);
    }
    $shipment->save();
    $commerce_order->set('shipments', $shipment);
    $commerce_order->setData(ShippingOrderManagerInterface::FORCE_REFRESH, TRUE);
    $commerce_order->save();

    return new JsonResponse([
      'lineItems' => $this->expressCheckoutHelper->getOrderLineItems($commerce_order),
      'shippingRates' => $this->getFormattedShippingRates($shipment),
      'amount' => $this->minorUnitsConverter->toMinorUnits($commerce_order->getTotalPrice()),
    ]);
  }

  /**
   * Reacts on shipping rate change event.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response.
   */
  public function onShippingRateChange(OrderInterface $commerce_order, Request $request): Response {
    $body = Json::decode($request->getContent());
    if (!isset($body['shippingRate'])) {
      return new Response('Missing shipping rate', 500);
    }
    $shipping_rate = $body['shippingRate'];
    $shipment = NULL;

    if ($this->shippingOrderManager->hasShipments($commerce_order)) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
      $shipments = $commerce_order->get('shipments')->referencedEntities();
      $shipment = reset($shipments);
      // Apply the selected shipping rate to the order.
      $shipment->setShippingMethodId($shipping_rate['id']);
      $shipment->save();
      $commerce_order->setData(ShippingOrderManagerInterface::FORCE_REFRESH, TRUE);
      $commerce_order->save();
    }

    return new JsonResponse([
      'lineItems' => $this->expressCheckoutHelper->getOrderLineItems($commerce_order),
      'shippingRates' => $shipment ? $this->getFormattedShippingRates($shipment) : [],
      'amount' => $this->minorUnitsConverter->toMinorUnits($commerce_order->getTotalPrice()),
    ]);
  }

  /**
   * Reacts on cancel event.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response.
   */
  public function onCancel(OrderInterface $commerce_order): Response {
    if ($commerce_order->hasField('shipments') &&
      !$commerce_order->get('shipments')->isEmpty()) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
      $shipments = $commerce_order->get('shipments')->referencedEntities();
      foreach ($shipments as $shipment) {
        $shipment->delete();
      }
      $commerce_order->set('shipments', []);
    }
    $commerce_order->unsetData('stripe_express_checkout');
    $this->clearOrderCheckoutData($commerce_order);
    $commerce_order->save();

    return new JsonResponse([
      'amount' => $this->minorUnitsConverter->toMinorUnits($commerce_order->getTotalPrice()),
    ]);
  }

  /**
   * Reacts on shipping address validate event.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response.
   */
  public function onValidateShippingAddress(OrderInterface $commerce_order, Request $request): Response {
    $body = Json::decode($request->getContent());
    $shipping_address = $body['shippingAddress'] ?? NULL;
    $is_address_valid = TRUE;

    if (!empty($shipping_address)) {
      $address = $shipping_address['address'];
      // Note, that 'state' is not required for all countries. Therefore it is
      // not included here.
      foreach (['city', 'country', 'line1', 'postal_code'] as $key) {
        $validate = trim($address[$key]);
        if (empty($validate)) {
          $is_address_valid = FALSE;
          break;
        }
      }

      $name = trim($shipping_address['name']);
      if (empty($name)) {
        $is_address_valid = FALSE;
      }
    }

    return new JsonResponse([
      'isValidShippingAddress' => $is_address_valid,
    ]);
  }

  /**
   * Gets the shipment type for the current order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return string
   *   The shipment type.
   */
  protected function getShipmentType(OrderInterface $order): string {
    $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $order_type_storage->load($order->bundle());

    return $order_type->getThirdPartySetting('commerce_shipping', 'shipment_type');
  }

  /**
   * Gets the shipping profile with the updated address.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param array $shipping_address
   *   The shipping address.
   * @param string $name
   *   Recipient name.
   *
   * @return \Drupal\profile\Entity\ProfileInterface
   *   The shipping profile.
   */
  protected function getShippingProfile(OrderInterface $order, array $shipping_address, string $name): ProfileInterface {
    $shipping_profile = $this->shippingOrderManager->getProfile($order);
    if (!$shipping_profile) {
      $shipping_profile = $this->shippingOrderManager->createProfile($order);
    }

    $new_address = [
      'locality' => $shipping_address['city'] ?? '',
      'administrative_area' => $this->getAdministrativeAreaForAddress($shipping_address),
      'postal_code' => $shipping_address['postal_code'] ?? '',
      'country_code' => $shipping_address['country'],
    ];
    // Save the first part of the name as given_name and the rest
    // as family_name.
    if (!empty($name)) {
      $name_parts = explode(' ', $name);
      $new_address['given_name'] = $name_parts[0];
      $new_address['family_name'] = trim(str_replace($name_parts[0], '', $name));
    }
    $shipping_profile->set('address', $new_address);
    $shipping_profile->save();

    return $shipping_profile;
  }

  /**
   * Gets the administrative area for the address.
   *
   * @param array $shipping_address
   *   The shipping address.
   *
   * @return string|null
   *   Administrative area for the specified address or NULL if the country
   *   does not have administrative areas.
   */
  protected function getAdministrativeAreaForAddress(array $shipping_address): ?string {
    $subdivisions = $this->subdivisionRepository->getList([$shipping_address['country']]);

    if (in_array($shipping_address['state'], array_keys($subdivisions))) {
      return $shipping_address['state'];
    }
    foreach ($subdivisions as $key => $subdivision) {
      if (strtolower($shipping_address['state']) == strtolower($subdivision)) {
        return $key;
      }
    }

    return $shipping_address['state'];
  }

  /**
   * Gets all eligible shipping methods for the given shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return array
   *   The shipping methods.
   */
  protected function getShippingMethodsForShipment(ShipmentInterface $shipment): array {
    /** @var \Drupal\commerce_shipping\ShippingMethodStorageInterface $shipping_method_storage */
    $shipping_method_storage = $this->entityTypeManager->getStorage('commerce_shipping_method');
    return $shipping_method_storage->loadMultipleForShipment($shipment);
  }

  /**
   * Gets formatted shipping rates for the given shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return array
   *   Formatted shipping rates.
   */
  protected function getFormattedShippingRates(ShipmentInterface $shipment): array {
    $shipping_rates = $this->shipmentManager->calculateRates($shipment);

    $formatted_shipping_rates = [];
    foreach ($shipping_rates as $shipping_rate) {
      $formatted_shipping_rates[] = [
        'id' => $shipping_rate->getShippingMethodId(),
        'displayName' => $shipping_rate->getService()->getLabel(),
        'amount' => $this->minorUnitsConverter->toMinorUnits($shipping_rate->getAmount()),
      ];
    }

    return $formatted_shipping_rates;
  }

  /**
   * Clears order checkout data.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  protected function clearOrderCheckoutData(OrderInterface $order): void {
    if ($billing_profile = $order->getBillingProfile()) {
      $billing_profile->delete();
      $order->set('billing_profile', NULL);
    }
    // Cancel the payment intent if it was created.
    $intent_id = $order->getData('stripe_intent');
    if (!empty($intent_id)) {
      $order->unsetData('stripe_intent');
      if (!$order->get('payment_gateway')->isEmpty()) {
        // Get the payment gateway plugin to initialize Stripe.
        $payment_gateway = $order->get('payment_gateway')->entity;
        $payment_gateway->getPlugin();
        try {
          $intent = PaymentIntent::retrieve($intent_id);
          $intent->cancel();
        }
        catch (ApiErrorException $e) {
          ErrorHelper::handleException($e);
        }
      }
    }
    $order->set('payment_gateway', NULL);
    $order->set('payment_method', NULL);
    $order->set('checkout_step', NULL);
  }

}
