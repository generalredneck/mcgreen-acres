<?php

namespace Drupal\commerce_invoice\Mail;

use Drupal\commerce_invoice\Entity\InvoiceInterface;

interface InvoiceConfirmationMailInterface {

  /**
   * Sends the invoice confirmation email.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice
   *   The invoice.
   * @param string $to
   *   The address the email will be sent to. Must comply with RFC 2822.
   *   Defaults to the invoice email.
   * @param string $bcc
   *   The BCC address or addresses (separated by a comma).
   *
   * @return bool
   *   TRUE if the email was sent successfully, FALSE otherwise.
   */
  public function send(InvoiceInterface $invoice, $to = NULL, $bcc = NULL);

}
