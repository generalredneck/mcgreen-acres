<?php

namespace Drupal\commerce_invoice\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_invoice\Entity\InvoiceInterface;

/**
 * Defines the invoice filename event.
 *
 * @see \Drupal\commerce_invoice\Event\InvoiceEvents
 */
class InvoiceFilenameEvent extends EventBase {

  /**
   * The invoice filename.
   *
   * @var array
   */
  protected $filename;

  /**
   * The invoice.
   *
   * @var \Drupal\commerce_invoice\Entity\InvoiceInterface
   */
  protected $invoice;

  /**
   * Constructs a new InvoiceFilenameEvent.
   *
   * @param string $filename
   *   The invoice filename.
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice
   *   The invoice.
   */
  public function __construct($filename, InvoiceInterface $invoice) {
    $this->filename = $filename;
    $this->invoice = $invoice;
  }

  /**
   * Gets the invoice filename.
   *
   * @return string
   *   The invoice filename.
   */
  public function getFilename(): string {
    return $this->filename;
  }

  /**
   * Sets the invoice filename.
   *
   * @param string $filename
   *   The invoice filename.
   *
   * @return $this
   */
  public function setFilename(string $filename) {
    $this->filename = $filename;
    return $this;
  }

  /**
   * Gets the invoice.
   *
   * @return \Drupal\commerce_invoice\Entity\InvoiceInterface
   *   The invoice.
   */
  public function getInvoice(): InvoiceInterface {
    return $this->invoice;
  }

}
