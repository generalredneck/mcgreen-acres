<?php

namespace Drupal\commerce_invoice;

use Drupal\commerce\Utility\Error;
use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_order\EntityAdjustableInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Psr\Log\LoggerInterface;

class InvoiceGenerator implements InvoiceGeneratorInterface {

  /**
   * Constructs a new InvoiceGenerator object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(protected Connection $connection, protected EntityTypeManagerInterface $entityTypeManager, protected LanguageManagerInterface $languageManager, protected ModuleHandlerInterface $moduleHandler, protected LoggerInterface $logger) {}

  /**
   * {@inheritdoc}
   */
  public function generate(array $orders, StoreInterface $store, ?ProfileInterface $profile = NULL, array $values = [], $save = TRUE) {
    $transaction = $this->connection->startTransaction();
    try {
      return $this->doGenerate($orders, $store, $profile, $values, $save);
    }
    catch (\Exception $exception) {
      $transaction->rollBack();
      Error::logException($this->logger, $exception);
      return NULL;
    }
  }

  /**
   * @see \Drupal\commerce_invoice\InvoiceGeneratorInterface::generate()
   */
  protected function doGenerate(array $orders, StoreInterface $store, ?ProfileInterface $profile = NULL, array $values = [], $save = TRUE) {
    $invoice_storage = $this->entityTypeManager->getStorage('commerce_invoice');

    $values += [
      'store_id' => $store->id(),
      'billing_profile' => $profile,
    ];

    // If we're not generating an invoice for a single order, don't inherit its
    // customer information and payment method.
    if (count($orders) !== 1) {
      $values += [
        'mail' => NULL,
        'uid' => NULL,
        'payment_method' => NULL,
      ];
    }

    // Assume the order type from the first passed order, we'll use it
    // to determine the invoice type to create.
    /** @var \Drupal\commerce_order\Entity\OrderInterface $first_order */
    $first_order = reset($orders);

    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $invoice_storage->createFromOrder($first_order, $values);

    // Find any (partial) invoices that reference the given orders so we can
    // subtract their adjustments and invoice items quantities and adjustments.
    $existing_invoices = $invoice_storage->loadByOrders($invoice->bundle(), $orders);

    $total_paid = NULL;
    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $orders */
    foreach ($orders as $order) {
      $existing_invoices_for_order = array_filter($existing_invoices, function (InvoiceInterface $invoice) use ($order) {
        $order_ids = array_column($invoice->get('orders')->getValue(), 'target_id');
        return in_array($order->id(), $order_ids);
      });

      // Copy over all the adjustments from the order, if there any left after
      // taking into account those that were applied to previous invoices.
      foreach ($this->getAdjustmentsFromEntity($order, $existing_invoices_for_order) as $adjustment) {
        $invoice->addAdjustment($adjustment);
      }

      foreach ($this->getInvoiceItemsFromOrder($order, $invoice, $existing_invoices_for_order) as $invoice_item) {
        if ($save) {
          $invoice_item->save();
        }
        $invoice->addItem($invoice_item);
      }

      $total_paid = $total_paid ? $total_paid->add($order->getTotalPaid()) : $order->getTotalPaid();
    }

    $invoice->setOrders($orders);

    if ($invoice->getState()->getId() === 'draft') {
      $invoice->getState()->applyTransitionById('confirm');
    }

    if ($total_paid) {
      $invoice->setTotalPaid($total_paid);
    }

    if ($save) {
      $invoice->save();
    }
    return $invoice;
  }

  /**
   * Return an array of adjustments from a given adjustable entity.
   *
   * @param \Drupal\commerce_order\EntityAdjustableInterface $entity
   *   An adjustable entity object.
   * @param \Drupal\commerce_order\EntityAdjustableInterface[] $existing_items
   *   (optional) An array of existing items that might contain adjustments
   *   which need to be subtracted from adjustable entity above. Defaults to an
   *   empty array.
   *
   * @return \Drupal\commerce_order\Adjustment[]
   *   An array of adjustments.
   */
  private function getAdjustmentsFromEntity(EntityAdjustableInterface $entity, array $existing_items = []) {
    $adjustments = [];
    foreach ($entity->getAdjustments() as $adjustment) {
      // Look through all the existing invoices for this order and subtract the
      // amount of their adjustments.
      foreach ($existing_items as $existing_item) {
        foreach ($existing_item->getAdjustments() as $previous_adjustment) {
          if ($adjustment->getType() === $previous_adjustment->getType() && $adjustment->getSourceId() === $previous_adjustment->getSourceId()) {
            $adjustment = $adjustment->subtract($previous_adjustment);
          }
        }
      }

      $adjustments[] = $adjustment;
    }

    return $adjustments;
  }

  /**
   * Return an array of invoice items from a given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice
   *   An invoice.
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface[] $existing_invoices
   *   (optional) An array of existing (partial) invoices for this order.
   *   Defaults to an empty array.
   *
   * @return \Drupal\commerce_invoice\Entity\InvoiceItemInterface[]
   *   An array of invoice items.
   */
  private function getInvoiceItemsFromOrder(OrderInterface $order, InvoiceInterface $invoice, array $existing_invoices = []) {
    $invoice_items = [];

    // Get the default invoice language so we can set it on invoice items.
    $default_langcode = $invoice->language()->getId();
    $invoice_item_storage = $this->entityTypeManager->getStorage('commerce_invoice_item');

    foreach ($order->getItems() as $order_item) {
      /** @var \Drupal\commerce_order\Entity\OrderItemTypeInterface $order_item_type */
      $order_item_type = OrderItemType::load($order_item->bundle());
      $invoice_item_type = $order_item_type->getPurchasableEntityTypeId() ?: 'default';
      /** @var \Drupal\commerce_invoice\Entity\InvoiceItemInterface $invoice_item */
      $invoice_item = $invoice_item_storage->create([
        'langcode' => $default_langcode,
        'type' => $invoice_item_type,
      ]);

      $invoice_item->populateFromOrderItem($order_item);

      // Look through all the existing invoices for this order and subtract the
      // quantity of their matching invoice items. We don't need to this for
      // each invoice item translation below because
      // InvoiceItem::populateFromOrderItem() only sets the quantity value on
      // the default translation.
      foreach ($existing_invoices as $existing_invoice) {
        foreach ($existing_invoice->getItems() as $previous_invoice_item) {
          if ($invoice_item->getOrderItemId() == $previous_invoice_item->getOrderItemId()) {
            $new_quantity = Calculator::subtract($invoice_item->getQuantity(), $previous_invoice_item->getQuantity());
            $invoice_item->setQuantity($new_quantity);
          }
        }
      }

      // Ensure that order items that have corresponding quantity value in the
      // existing (partial) invoices can not be added to a new invoice.
      if (Calculator::compare($invoice_item->getQuantity(), '0') == 0) {
        continue;
      }

      // Look through all the existing invoices for this order and subtract the
      // adjustments of their matching invoice items.
      $previous_invoice_items = [];
      foreach ($existing_invoices as $existing_invoice) {
        $previous_invoice_items = array_merge($previous_invoice_items, array_filter($existing_invoice->getItems(), function ($previous_invoice_item) use ($invoice_item) {
          return $invoice_item->getOrderItemId() == $previous_invoice_item->getOrderItemId();
        }));
      }
      $invoice_item->setAdjustments($this->getAdjustmentsFromEntity($order_item, $previous_invoice_items));

      // If the invoice is translated, we need to generate translations in
      // all languages for each invoice item.
      foreach ($invoice->getTranslationLanguages(FALSE) as $langcode => $language) {
        $translated_invoice_item = $invoice_item->addTranslation($langcode);
        // We're calling InvoiceItem::populateFromOrderItem() for each
        // translation since that logic is responsible for pulling the
        // translated variation title, if available.
        $translated_invoice_item->populateFromOrderItem($order_item);
      }

      $invoice_items[] = $invoice_item;
    }

    return $invoice_items;
  }

}
