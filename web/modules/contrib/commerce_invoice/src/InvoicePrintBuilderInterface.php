<?php

namespace Drupal\commerce_invoice;

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\entity_print\Plugin\PrintEngineInterface;

/**
 * Handles generating PDFS for invoices.
 */
interface InvoicePrintBuilderInterface {

  /**
   * Generates a filename for the given invoice.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice
   *   The invoice.
   *
   * @return string
   *   The generated filename.
   */
  public function generateFilename(InvoiceInterface $invoice);

  /**
   * Renders the invoice as a printed document and save to disk.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice
   *   The invoice.
   * @param \Drupal\entity_print\Plugin\PrintEngineInterface $print_engine
   *   The print engine plugin to use.
   * @param string $scheme
   *   (optional) The Drupal scheme, defaults to 'private'.
   *
   * @return \Drupal\file\FileInterface|null
   *   The invoice PDF file, FALSE it could not be created.
   */
  public function savePrintable(InvoiceInterface $invoice, PrintEngineInterface $print_engine, $scheme = 'private');

}
