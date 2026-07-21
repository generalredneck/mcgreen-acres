<?php

namespace Drupal\commerce_invoice;

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\system\PathBasedBreadcrumbBuilder;

/**
 * Defines the Commerce Invoice breadcrumb builder.
 */
class InvoiceBreadcrumbBuilder extends PathBasedBreadcrumbBuilder {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL) {
    // Do not apply to user view.
    if ($route_match->getRouteName() === 'entity.commerce_invoice.user_view') {
      return FALSE;
    }
    // This breadcrumb builder applies only when an invoice corresponds to a
    // single order.
    $invoice = $route_match->getParameter('commerce_invoice');
    return $invoice instanceof InvoiceInterface && $invoice->get('orders')->count() === 1;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $current_path_info = $this->context->getPathInfo();

    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $route_match->getParameter('commerce_invoice');
    $path = $invoice->toUrl('collection')->getInternalPath();
    $this->context->setPathInfo('/' . trim($path, '/') . '/' . $invoice->id());
    $breadcrumb = parent::build($route_match);

    // Restore the initial request path info.
    $this->context->setPathInfo($current_path_info);

    return $breadcrumb;
  }

}
