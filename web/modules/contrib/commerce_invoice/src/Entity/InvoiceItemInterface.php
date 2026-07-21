<?php

namespace Drupal\commerce_invoice\Entity;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\EntityAdjustableInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Defines the interface for invoice items.
 */
interface InvoiceItemInterface extends ContentEntityInterface, EntityAdjustableInterface, EntityChangedInterface {

  /**
   * Gets the parent invoice.
   *
   * @return \Drupal\commerce_invoice\Entity\InvoiceInterface|null
   *   The invoice, or NULL.
   */
  public function getInvoice();

  /**
   * Gets the parent invoice ID.
   *
   * @return int|null
   *   The invoice ID, or NULL.
   */
  public function getInvoiceId();

  /**
   * Gets the invoice item title.
   *
   * @return string
   *   The invoice item title
   */
  public function getTitle();

  /**
   * Sets the invoice item title.
   *
   * @param string $title
   *   The invoice item title.
   *
   * @return $this
   */
  public function setTitle($title);

  /**
   * Gets the invoice item description.
   *
   * @return string
   *   The invoice item description.
   */
  public function getDescription();

  /**
   * Sets the invoice item description.
   *
   * @param string $description
   *   The invoice item description.
   *
   * @return $this
   */
  public function setDescription($description);

  /**
   * Gets the text format name for the invoice item description.
   *
   * @return string
   *   The text format name.
   */
  public function getFormat();

  /**
   * Sets the text format name for the invoice item description.
   *
   * @param string $format
   *   The text format name.
   *
   * @return $this
   */
  public function setFormat($format);

  /**
   * Gets the invoice item quantity.
   *
   * @return string
   *   The invoice item quantity
   */
  public function getQuantity();

  /**
   * Sets the invoice item quantity.
   *
   * @param string $quantity
   *   The invoice item quantity.
   *
   * @return $this
   */
  public function setQuantity($quantity);

  /**
   * Gets the invoice item unit price.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The invoice item unit price, or NULL.
   */
  public function getUnitPrice();

  /**
   * Sets the invoice item unit price.
   *
   * @param \Drupal\commerce_price\Price $unit_price
   *   The invoice item unit price.
   *
   * @return $this
   */
  public function setUnitPrice(Price $unit_price);

  /**
   * Gets the invoice item total price.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The invoice item total price, or NULL.
   */
  public function getTotalPrice();

  /**
   * Gets the adjusted invoice item total price.
   *
   * The adjusted total price is calculated by applying the order item's
   * adjustments to the total price. This can include promotions, taxes, etc.
   *
   * @param string[] $adjustment_types
   *   The adjustment types to include in the adjusted price.
   *   Examples: fee, promotion, tax. Defaults to all adjustment types.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The adjusted invoice item total price, or NULL.
   */
  public function getAdjustedTotalPrice(array $adjustment_types = []);

  /**
   * Gets the adjusted invoice item unit price.
   *
   * Calculated by dividing the adjusted total price by quantity.
   *
   * Useful for refunds and other purposes where there's a need to know
   * how much a single unit contributed to the order total.
   *
   * @param string[] $adjustment_types
   *   The adjustment types to include in the adjusted price.
   *   Examples: fee, promotion, tax. Defaults to all adjustment types.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The adjusted invoice item unit price, or NULL.
   */
  public function getAdjustedUnitPrice(array $adjustment_types = []);

  /**
   * Gets an invoice item data value with the given key.
   *
   * Used to store temporary data.
   *
   * @param string $key
   *   The key.
   * @param mixed $default
   *   The default value.
   *
   * @return mixed
   *   The value.
   */
  public function getData($key, $default = NULL);

  /**
   * Sets an invoice item data value with the given key.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   */
  public function setData($key, $value);

  /**
   * Unsets an invoice item data value with the given key.
   *
   * @param string $key
   *   The key.
   *
   * @return $this
   */
  public function unsetData($key);

  /**
   * Gets the invoice item creation timestamp.
   *
   * @return int
   *   The invoice item creation timestamp.
   */
  public function getCreatedTime();

  /**
   * Sets the invoice item creation timestamp.
   *
   * @param int $timestamp
   *   The invoice item creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the order item.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface|null
   *   The order item, or NULL.
   */
  public function getOrderItem();

  /**
   * Gets the order item ID.
   *
   * @return int|null
   *   The order item ID, or NULL.
   */
  public function getOrderItemId();

  /**
   * Populates the invoice item with field values from the order item.
   *
   * @param \Drupal\commerce_Order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @return $this
   */
  public function populateFromOrderItem(OrderItemInterface $order_item);

}
