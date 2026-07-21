<?php

namespace Drupal\commerce_invoice;

use Drupal\commerce_invoice\Entity\InvoiceInterface;

/**
 * Manages the invoice file.
 */
interface InvoiceFileManagerInterface {

  /**
   * Gets the file for an invoice.
   *
   * If the file does not exist, a new PDF file is generated, and the
   * reference field on the invoice is set.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice
   *   The invoice, NULL if not found.
   * @param bool|null $skip_save
   *   Whether the invoice should be saved. Defaults to FALSE.
   *   The file reference is not saved automatically if this is TRUE.
   *
   * @return \Drupal\file\FileInterface|null
   *   The invoice file, NULL if the invoice could not be generated.
   */
  public function getInvoiceFile(InvoiceInterface $invoice, ?bool $skip_save = FALSE);

}
