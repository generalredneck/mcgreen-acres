<?php

namespace Drupal\commerce_invoice\Mail;

use Drupal\commerce\MailHandlerInterface;
use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\commerce_invoice\InvoiceFileManagerInterface;
use Drupal\commerce_invoice\InvoiceTotalSummaryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class InvoiceConfirmationMail implements InvoiceConfirmationMailInterface {

  use StringTranslationTrait;

  /**
   * The profile view builder.
   *
   * @var \Drupal\profile\ProfileViewBuilder
   */
  protected $profileViewBuilder;

  /**
   * Constructs a new InvoiceConfirmationMail object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce\MailHandlerInterface $mailHandler
   *   The mail handler.
   * @param \Drupal\commerce_invoice\InvoiceTotalSummaryInterface $invoiceTotalSummary
   *   The invoice total summary.
   * @param \Drupal\commerce_invoice\InvoiceFileManagerInterface $invoiceFileManager
   *   The invoice file manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    protected MailHandlerInterface $mailHandler,
    protected InvoiceTotalSummaryInterface $invoiceTotalSummary,
    protected InvoiceFileManagerInterface $invoiceFileManager,
    protected FileSystemInterface $fileSystem,
  ) {
    $this->profileViewBuilder = $entityTypeManager->getViewBuilder('profile');
  }

  /**
   * {@inheritdoc}
   */
  public function send(InvoiceInterface $invoice, $to = NULL, $bcc = NULL) {
    $to = $to ?? $invoice->getEmail();
    if (!$to) {
      // The email should not be empty.
      return FALSE;
    }

    $subject = $this->t('Invoice #@number', ['@number' => $invoice->getInvoiceNumber()]);
    $body = [
      '#theme' => 'commerce_invoice_confirmation',
      '#invoice_entity' => $invoice,
      '#totals' => $this->invoiceTotalSummary->buildTotals($invoice),
    ];
    if ($billing_profile = $invoice->getBillingProfile()) {
      $body['#billing_information'] = $this->profileViewBuilder->view($billing_profile);
    }

    $params = [
      'id' => 'invoice_confirmation',
      'from' => $invoice->getStore()->getEmail(),
      'bcc' => $bcc,
      'invoice' => $invoice,
    ];
    $customer = $invoice->getCustomer();
    if ($customer->isAuthenticated()) {
      $params['langcode'] = $customer->getPreferredLangcode();
    }
    $file = $this->invoiceFileManager->getInvoiceFile($invoice);
    if ($file) {
      $attachment = [
        'filepath' => $this->fileSystem->realpath($file->getFileUri()),
        'filename' => $file->getFilename(),
        'filemime' => $file->getMimeType(),
      ];
      $params['attachments'][] = $attachment;
    }

    return $this->mailHandler->sendMail($to, $subject, $body, $params);
  }

}
