<?php

namespace Drupal\commerce_invoice\Entity;

use Drupal\commerce_order\EntityAdjustableInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_store\Entity\EntityStoreInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\file\FileInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserInterface;

/**
 * Defines the interface for invoices.
 */
interface InvoiceInterface extends ContentEntityInterface, EntityAdjustableInterface, EntityChangedInterface, EntityStoreInterface {

  /**
   * Gets the invoice number.
   *
   * @return string
   *   The invoice number.
   */
  public function getInvoiceNumber();

  /**
   * Sets the invoice number.
   *
   * @param string $invoice_number
   *   The invoice number.
   *
   * @return $this
   */
  public function setInvoiceNumber($invoice_number);

  /**
   * Gets the customer user.
   *
   * @return \Drupal\user\UserInterface
   *   The customer user entity. If the invoice is anonymous (customer
   *   unspecified or deleted), an anonymous user will be returned. Use
   *   $customer->isAnonymous() to check.
   */
  public function getCustomer();

  /**
   * Sets the customer user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The customer user entity.
   *
   * @return $this
   */
  public function setCustomer(UserInterface $account);

  /**
   * Gets the customer user ID.
   *
   * @return int
   *   The customer user ID ('0' if anonymous).
   */
  public function getCustomerId();

  /**
   * Gets the invoice email.
   *
   * @return string
   *   The invoice email.
   */
  public function getEmail();

  /**
   * Sets the invoice email.
   *
   * @param string $mail
   *   The invoice email.
   *
   * @return $this
   */
  public function setEmail($mail);

  /**
   * Sets the customer user ID.
   *
   * @param int $uid
   *   The customer user ID.
   *
   * @return $this
   */
  public function setCustomerId($uid);

  /**
   * Gets the billing profile.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The billing profile, or NULL if none found.
   */
  public function getBillingProfile();

  /**
   * Sets the billing profile.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The billing profile.
   *
   * @return $this
   */
  public function setBillingProfile(ProfileInterface $profile);

  /**
   * Gets the invoice orders.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface[]
   *   The invoice orders.
   */
  public function getOrders();

  /**
   * Sets the invoice orders.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface[] $orders
   *   The invoice orders.
   *
   * @return $this
   */
  public function setOrders(array $orders);

  /**
   * Gets the invoice items.
   *
   * @return \Drupal\commerce_invoice\Entity\InvoiceItemInterface[]
   *   The invoice items.
   */
  public function getItems();

  /**
   * Sets the invoice items.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceItemInterface[] $invoice_items
   *   The invoice items.
   *
   * @return $this
   */
  public function setItems(array $invoice_items);

  /**
   * Gets whether the invoice has invoice items.
   *
   * @return bool
   *   TRUE if the invoice has invoice items, FALSE otherwise.
   */
  public function hasItems();

  /**
   * Adds an invoice item.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceItemInterface $invoice_item
   *   The invoice item.
   *
   * @return $this
   */
  public function addItem(InvoiceItemInterface $invoice_item);

  /**
   * Removes an invoice item.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceItemInterface $invoice_item
   *   The invoice item.
   *
   * @return $this
   */
  public function removeItem(InvoiceItemInterface $invoice_item);

  /**
   * Checks whether the invoice has a given invoice item.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceItemInterface $invoice_item
   *   The invoice item.
   *
   * @return bool
   *   TRUE if the invoice item was found, FALSE otherwise.
   */
  public function hasItem(InvoiceItemInterface $invoice_item);

  /**
   * Collects all adjustments that belong to the invoice.
   *
   * Unlike getAdjustments() which returns only invoice adjustments, this
   * method returns both invoice and invoice item adjustments.
   *
   * Important:
   * The returned adjustments are unprocessed, and must be processed before use.
   *
   * @param string[] $adjustment_types
   *   The adjustment types to include.
   *   Examples: fee, promotion, tax. Defaults to all adjustment types.
   *
   * @return \Drupal\commerce_order\Adjustment[]
   *   The adjustments.
   *
   * @see \Drupal\commerce_order\AdjustmentTransformerInterface::processAdjustments()
   */
  public function collectAdjustments(array $adjustment_types = []);

  /**
   * Gets the payment method.
   *
   * @return string
   *   The payment method.
   */
  public function getPaymentMethod();

  /**
   * Sets the payment method.
   *
   * @param string $payment_method
   *   The payment method.
   *
   * @return $this
   */
  public function setPaymentMethod($payment_method);

  /**
   * Gets the invoice subtotal price.
   *
   * Represents a sum of all invoice item totals.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The invoice subtotal price, or NULL.
   */
  public function getSubtotalPrice();

  /**
   * Recalculates the invoice total price.
   *
   * @return $this
   */
  public function recalculateTotalPrice();

  /**
   * Gets the invoice total price.
   *
   * Represents a sum of all invoice item totals.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The invoice total price, or NULL.
   */
  public function getTotalPrice();

  /**
   * Gets the total paid price.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The total paid price, or NULL.
   */
  public function getTotalPaid();

  /**
   * Sets the total paid price.
   *
   * @param \Drupal\commerce_price\Price $total_paid
   *   The total paid price.
   */
  public function setTotalPaid(Price $total_paid);

  /**
   * Gets the invoice balance.
   *
   * Calculated by subtracting the total paid price from the total price.
   * Can be negative in case the invoice was overpaid.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The invoice balance, or NULL.
   */
  public function getBalance();

  /**
   * Gets whether the invoice has been fully paid.
   *
   * Invoices are considered fully paid once their balance
   * becomes zero or negative.
   *
   * @return bool
   *   TRUE if the invoice has been fully paid, FALSE otherwise.
   */
  public function isPaid();

  /**
   * Gets the invoice state.
   *
   * @return \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface
   *   The invoice state.
   */
  public function getState();

  /**
   * Gets an invoice data value with the given key.
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
   * Sets an invoice data value with the given key.
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
   * Gets the invoice creation timestamp.
   *
   * @return int
   *   Creation timestamp of the invoice.
   */
  public function getCreatedTime();

  /**
   * Sets the invoice creation timestamp.
   *
   * @param int $timestamp
   *   The invoice creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the invoice date timestamp.
   *
   * @return int
   *   Date timestamp of the invoice.
   */
  public function getInvoiceDateTime();

  /**
   * Sets the invoice date timestamp.
   *
   * @param int $timestamp
   *   The invoice date timestamp.
   *
   * @return $this
   */
  public function setInvoiceDateTime($timestamp);

  /**
   * Gets the invoice due date timestamp.
   *
   * @return int
   *   Due date timestamp of the invoice.
   */
  public function getDueDateTime();

  /**
   * Sets the invoice due date timestamp.
   *
   * @param int $timestamp
   *   The invoice due date timestamp.
   *
   * @return $this
   */
  public function setDueDateTime($timestamp);

  /**
   * Gets the invoice file.
   *
   * @return \Drupal\file\FileInterface|null
   *   The invoice file, NULL if empty.
   */
  public function getFile();

  /**
   * Sets the invoice file (i.e the reference to the generated PDF file).
   *
   * @param \Drupal\file\FileInterface $file
   *   The invoice file.
   *
   * @return $this
   */
  public function setFile(FileInterface $file);

}
