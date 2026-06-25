<?php

namespace Drupal\commerce_invoice\Controller;

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\commerce_invoice\Entity\InvoiceTypeInterface;
use Drupal\commerce_invoice\InvoiceFileManagerInterface;
use Drupal\commerce_invoice\InvoiceGeneratorInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides the invoice download route.
 */
class InvoiceController extends ControllerBase implements ContainerInjectionInterface {

  use DependencySerializationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The invoice file manager.
   *
   * @var \Drupal\commerce_invoice\InvoiceFileManagerInterface
   */
  protected $invoiceFileManager;

  /**
   * The invoice generator.
   *
   * @var \Drupal\commerce_invoice\InvoiceGeneratorInterface
   */
  protected $invoiceGenerator;

  /**
   * Constructs a new InvoiceController object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\commerce_invoice\InvoiceFileManagerInterface $invoice_file_manager
   *   The invoice file manager.
   * @param \Drupal\commerce_invoice\InvoiceGeneratorInterface $invoice_generator
   *   The invoice generator.
   */
  public function __construct(ConfigFactoryInterface $config_factory, InvoiceFileManagerInterface $invoice_file_manager, InvoiceGeneratorInterface $invoice_generator) {
    $this->configFactory = $config_factory;
    $this->invoiceFileManager = $invoice_file_manager;
    $this->invoiceGenerator = $invoice_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('commerce_invoice.invoice_file_manager'),
      $container->get('commerce_invoice.invoice_generator')
    );
  }

  /**
   * The _title_callback for the page that renders a single invoice.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $commerce_invoice
   *   The invoice being viewed.
   *
   * @return string
   *   The page title.
   */
  public function title(InvoiceInterface $commerce_invoice) {
    return $commerce_invoice->label();
  }

  /**
   * Returns a form to add a new invoice of a specific type.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The commerce order.
   * @param \Drupal\commerce_invoice\Entity\InvoiceTypeInterface $commerce_invoice_type
   *   The invoice type.
   *
   * @return array
   *   The invoice add form.
   */
  public function addForm(OrderInterface $commerce_order, InvoiceTypeInterface $commerce_invoice_type) {
    $invoice = $this->invoiceGenerator->generate(
      [$commerce_order],
      $commerce_order->getStore(),
      $commerce_order->getBillingProfile(),
      [
        'type' => $commerce_invoice_type->id(),
      ],
      FALSE
    );

    // The invoice generator automatically sets a value for the total paid price
    // but when adding an invoice manually (through the add form), we need to
    // let the store owner decide when an invoice has been paid.
    $total_paid = new Price('0', $commerce_order->getTotalPrice()->getCurrencyCode());
    $invoice->setTotalPaid($total_paid);

    return $this->entityFormBuilder()->getForm($invoice);
  }

  /**
   * Downloads an invoice.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the file was not found.
   */
  public function download(RouteMatchInterface $route_match) {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $route_match->getParameter('commerce_invoice');

    $file = $this->invoiceFileManager->getInvoiceFile($invoice);
    if (!$file) {
      throw new NotFoundHttpException();
    }
    $config = $this->configFactory->get('entity_print.settings');
    // Check whether we need to force the download.
    $content_disposition = $config->get('force_download') ? 'attachment' : NULL;
    $headers = file_get_content_headers($file);
    return new BinaryFileResponse($file->getFileUri(), 200, $headers, FALSE, $content_disposition);
  }

}
