<?php

namespace Drupal\facebook_pixel_commerce;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\RounderInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;

/**
 * Helper methods for facebook_pixel_commerce module.
 *
 * @package Drupal\facebook_pixel_commerce
 */
class FacebookCommerce implements FacebookCommerceInterface {

  /**
   * The rounder service.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * FacebookCommerce constructor.
   *
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The price rounder.
   */
  public function __construct(RounderInterface $rounder) {
    $this->rounder = $rounder;
  }

  /**
   * Build the Facebook object for orders.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order object.
   *
   * @return array
   *   The data array for an order.
   */
  public function getOrderData(OrderInterface $order) {
    if (!$order || !($order instanceof OrderInterface)) {
      return [];
    }

    $contents = [];
    $content_ids = [];

    $totalPrice = $order->getTotalPrice();
    $data = [
      // "0" needs to be a string as "getNumber()" returns a string as well:
      'value' => $totalPrice ? $this->rounder->round($totalPrice)->getNumber() : '0',
      'currency' => $totalPrice ? $totalPrice->getCurrencyCode() : '',
      'num_items' => count($order->getItems()),
      'content_name' => 'order',
      'content_type' => 'product',
    ];

    foreach ($order->getItems() as $order_item) {
      $item_data = $this->getOrderItemData($order_item);
      if (!empty($item_data['contents'][0])) {
        $contents[] = $item_data['contents'][0];
        $content_ids[] = $item_data['contents'][0]['id'];
      }
    }

    if (!empty($contents)) {
      $data['contents'] = $contents;
      $data['content_ids'] = $content_ids;
    }

    return $data;
  }

  /**
   * Build the Facebook object for order items.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item object.
   *
   * @return array
   *   The data array for an order item.
   */
  public function getOrderItemData(OrderItemInterface $order_item) {
    if (!$order_item || !($order_item instanceof OrderItemInterface)) {
      return [];
    }

    $purchased_entity = $order_item->getPurchasedEntity();
    $sku = $purchased_entity instanceof ProductVariationInterface ? $purchased_entity->getSku() : NULL; 
    $unitPrice = $order_item->getUnitPrice();
    $data = [
      // "0" needs to be a string as "getNumber()" returns a string as well:
      'value' => $unitPrice ? $this->rounder->round($unitPrice)->getNumber() : '0',
      'currency' => $unitPrice ? $unitPrice->getCurrencyCode() : '',
      'order_id' => $order_item->getOrderId(),
      'content_ids' => [$sku ?? $order_item->getPurchasedEntityId() ?? ''],
      'content_name' => $order_item->getTitle(),
      'content_type' => 'product',
      'contents' => [
        [
          'id' => $sku ?? $order_item->getPurchasedEntityId() ?? '',
          'quantity' => $order_item->getQuantity(),
        ],
      ],
    ];

    return $data;
  }

}
