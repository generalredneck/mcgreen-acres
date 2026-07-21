<?php

namespace Drupal\commerce_shipping\Hook;

use Drupal\commerce_shipping\OrderShipmentSummaryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;

/**
 * Theme hook implementations for Commerce Cart.
 */
class CommerceShippingThemeHooks {

  /**
   * Constructs a new CommerceShippingThemeHooks object.
   *
   * @param \Drupal\commerce_shipping\OrderShipmentSummaryInterface $orderShipmentSummary
   *   The order shipment summary.
   */
  public function __construct(
    protected readonly OrderShipmentSummaryInterface $orderShipmentSummary,
  ) {
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'commerce_shipment' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessCommerceShipment',
      ],
      'commerce_shipment_confirmation' => [
        'variables' => [
          'order_entity' => NULL,
          'shipment_entity' => NULL,
          'shipping_profile' => NULL,
          'tracking_code' => NULL,
        ],
      ],
      'commerce_shipping_resources' => [
        'variables' => [],
      ],
      'commerce_shipping_resources_installed' => [
        'variables' => [],
      ],
      'commerce_shipping_rates_empty' => [
        'variables' => [
          'attributes' => [],
          'shipment' => NULL,
        ],
      ],
      'commerce_shipping_information' => [
        'variables' => [
          'shipment_panels' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_commerce_shipment')]
  public function themeSuggestionsCommerceShipment(array $variables): array {
    return _commerce_entity_theme_suggestions('commerce_shipment', $variables);
  }

  /**
   * Prepares variables for shipment templates.
   *
   * Default template: commerce-shipment.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing rendered fields.
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessCommerceShipment(array &$variables): void {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $variables['elements']['#commerce_shipment'];

    $variables['shipment_entity'] = $shipment;
    $variables['shipment'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['shipment'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_commerce_order')]
  public function preprocessCommerceOrder(array &$variables): void {
    // We need to assure that the "shipments" field is not shown in order details.
    if (isset($variables['additional_order_fields'])) {
      unset($variables['additional_order_fields']['shipments']);
    }
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_commerce_order_receipt')]
  public function preprocessCommerceOrderReceipt(array &$variables): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $variables['order_entity'];
    $summary = $this->orderShipmentSummary->build($order);
    if (!empty($summary)) {
      $variables['shipping_information'] = $summary;
    }
  }

}
