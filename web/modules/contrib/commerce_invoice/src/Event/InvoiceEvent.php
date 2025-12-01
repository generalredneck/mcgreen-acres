<?php

namespace Drupal\commerce_invoice\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_invoice\Entity\InvoiceInterface;

/**
 * Defines the invoice event.
 *
 * @see \Drupal\commerce_invoice\Event\OrderEvents
 */
class InvoiceEvent extends EventBase {

  /**
   * The invoice.
   *
   * @var \Drupal\commerce_invoice\Entity\InvoiceInterface
   */
  protected $invoice;

  /**
   * Constructs a new InvoiceEvent.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice
   *   The invoice.
   */
  public function __construct(InvoiceInterface $invoice) {
    $this->invoice = $invoice;
  }

  /**
   * Gets the invoice.
   *
   * @return \Drupal\commerce_invoice\Entity\InvoiceInterface
   *   Gets the invoice.
   */
  public function getInvoice(): InvoiceInterface {
    return $this->invoice;
  }

}
