<?php

namespace Drupal\commerce_invoice;

use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\Core\Entity\EntityInterface;
use Drupal\language\Entity\ContentLanguageSettings;

class InvoiceStorage extends CommerceContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function invokeHook($hook, EntityInterface $entity) {
    if ($hook == 'presave') {
      // Invoice::preSave() has completed, now run the storage-level pre-save
      // tasks. These tasks can modify the invoice, so they need to run
      // before the entity/field hooks are invoked.
      $this->doInvoicePresave($entity);
    }

    parent::invokeHook($hook, $entity);
  }

  /**
   * Performs invoice-specific pre-save tasks.
   *
   * This includes:
   * - Recalculating the total price.
   * - Applying the "paid" transition.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice
   *   The invoice.
   */
  protected function doInvoicePresave(InvoiceInterface $invoice) {
    $invoice->recalculateTotalPrice();

    // Apply the "paid" transition when an invoice is paid.
    $original_paid = isset($invoice->original) ? $invoice->original->isPaid() : FALSE;
    if ($invoice->isPaid() && !$original_paid) {
      // Check if a "pay" transition exists for that workflow.
      if ($invoice->getState()->isTransitionAllowed('pay')) {
        // Clear the invoice file reference.
        $invoice->set('invoice_file', NULL);
        $invoice->getState()->applyTransitionById('pay');
      }
    }
  }

  /**
   * Creates an invoice and populates its values with the ones from the order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   An order entity.
   * @param array $values
   *   (optional) An array of values to set, keyed by property name.
   *
   * @return \Drupal\commerce_invoice\Entity\InvoiceInterface
   *   The created invoice.
   */
  public function createFromOrder(OrderInterface $order, array $values = []) {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = OrderType::load($order->bundle());
    $values += [
      'type' => $order_type->getThirdPartySetting('commerce_invoice', 'invoice_type', 'default'),
      'state' => 'draft',
      'store_id' => $order->getStoreId(),
      'mail' => $order->getEmail(),
      'uid' => $order->getCustomerId(),
      'billing_profile' => $order->getBillingProfile(),
    ];

    if (!array_key_exists('payment_method', $values)) {
      if ($order->hasField('payment_method') && !$order->get('payment_method')->isEmpty()) {
        if ($payment_method = $order->get('payment_method')->entity) {
          $values['payment_method'] = $payment_method->label();
        }
      }
      elseif ($order->hasField('payment_gateway') && !$order->get('payment_gateway')->isEmpty()) {
        if ($payment_gateway = $order->get('payment_gateway')->entity) {
          $values['payment_method'] = $payment_gateway->label();
        }
      }
    }

    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = parent::create($values);

    // If the invoice type is configured to do so, generate the translations
    // for all the available languages.
    if ($this->moduleHandler->moduleExists('language')) {
      $config = ContentLanguageSettings::loadByEntityTypeBundle('commerce_invoice', $invoice->bundle());
      if ($config && $config->getThirdPartySetting('commerce_invoice', 'generate_translations', FALSE)) {
        $languages = $this->languageManager->getLanguages();
        foreach ($languages as $langcode => $language) {
          if ($invoice->hasTranslation($langcode)) {
            continue;
          }
          // Currently, only the data field is translatable on invoices, we
          // store the invoice type data there and make sure the translated data
          // is stored inside Invoice::presave().
          $invoice->addTranslation($langcode, $invoice->toArray());
        }
      }
    }

    return $invoice;
  }

  /**
   * Loads one or more invoices that are attached to the given orders.
   *
   * @param string $invoice_type
   *   The invoice type.
   * @param \Drupal\commerce_order\Entity\OrderInterface[] $orders
   *   An array of order entities.
   *
   * @return \Drupal\commerce_invoice\Entity\InvoiceInterface[]
   *   The invoices attached to the given order.
   */
  public function loadByOrders($invoice_type, array $orders) {
    $order_ids = array_map(function (OrderInterface $order) {
      return $order->id();
    }, $orders);

    return $this->loadByProperties([
      'type' => $invoice_type,
      'orders' => $order_ids,
    ]);
  }

}
