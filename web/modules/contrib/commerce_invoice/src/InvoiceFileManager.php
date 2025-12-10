<?php

namespace Drupal\commerce_invoice;

use Drupal\commerce\Utility\Error;
use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\commerce_invoice\Entity\InvoiceType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Default implementation of the invoice file manager.
 */
class InvoiceFileManager implements InvoiceFileManagerInterface {

  /**
   * Constructs a new InvoiceFileManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface $pluginManager
   *   The Entity print plugin manager.
   * @param \Drupal\commerce_invoice\InvoicePrintBuilderInterface $printBuilder
   *   The print builder.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityPrintPluginManagerInterface $pluginManager,
    protected InvoicePrintBuilderInterface $printBuilder,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getInvoiceFile(InvoiceInterface $invoice, ?bool $skip_save = FALSE) {
    $file = $invoice->getFile();
    // If the invoice already references a file, check if the invoice file
    // exists in the filesystem, otherwise delete it.
    if ($file) {
      if (file_exists($file->getFileUri())) {
        return $file;
      }
      $file->delete();
      $invoice->set('invoice_file', NULL);
    }

    // Check if an invoice was already generated for the given invoice,
    // that is not referenced by the invoice.
    $file = $this->loadExistingFile($invoice);
    // If the invoice file hasn't been generated yet, generate it.
    if (!$file) {
      $file = $this->generateInvoiceFile($invoice);
    }

    if (!$file) {
      return NULL;
    }

    // Sets the PDF file reference field on the invoice.
    if (!$invoice->getFile() || $invoice->getFile()->id() !== $file->id()) {
      $invoice->setFile($file);
      if (!$skip_save) {
        $invoice->save();
      }
    }

    return $file;
  }

  /**
   * Generates a PDF file for the given invoice.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice
   *   The invoice.
   *
   * @return \Drupal\file\FileInterface|null
   *   The invoice file, NULL if the generation failed.
   */
  protected function generateInvoiceFile(InvoiceInterface $invoice) {
    try {
      /** @var \Drupal\entity_print\Plugin\PrintEngineInterface $print_engine */
      $print_engine = $this->pluginManager->createSelectedInstance('pdf');
      return $this->printBuilder->savePrintable($invoice, $print_engine);
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return NULL;
    }
  }

  /**
   * Load an existing generated PDF file for the given invoice if it exist.
   *
   * @return \Drupal\file\FileInterface|null
   *   The invoice file, NULL if no matching invoice file was found or if it
   *   does not exist.
   */
  protected function loadExistingFile(InvoiceInterface $invoice) {
    /** @var \Drupal\File\FileStorageInterface $file_storage */
    $file_storage = $this->entityTypeManager->getStorage('file');
    // In case the invoice doesn't reference a file, fallback to loading a
    // file matching the given filename.
    $filename = $this->printBuilder->generateFilename($invoice);
    $invoice_type = InvoiceType::load($invoice->bundle());
    if ($invoice_type?->getPrivateSubdirectory()) {
      $filename = $invoice_type->getPrivateSubdirectory() . '/' . $filename;
    }

    $langcode = $invoice->language()->getId();
    $files = $file_storage->loadByProperties([
      'uri' => "private://$filename",
      'langcode' => $langcode,
    ]);

    if (!$files) {
      return NULL;
    }

    /** @var \Drupal\File\FileInterface $file */
    $file = $file_storage->load(key($files));
    if (!file_exists($file->getFileUri())) {
      $file->delete();
      return NULL;
    }

    return $file;
  }

}
