<?php

namespace Drupal\commerce_invoice\EventSubscriber;

use Drupal\commerce_invoice\Controller\InvoiceController;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Re-Add the route requirement for the order invoices route.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // Ensure to run after the Views route subscriber.
    // @see \Drupal\views\EventSubscriber\RouteSubscriber.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -200];

    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('entity.commerce_order.invoices');
    if ($route) {
      $route->setRequirement('_invoice_order_access', 'TRUE');
      $route->setDefault('commerce_invoice_type', 'default');
    }

    $route = $collection->get('entity.commerce_order.invoice_add_form');
    if ($route) {
      $route->setDefault('commerce_invoice_type', 'default');
    }

    $route = $collection->get('entity.commerce_invoice.canonical');
    if ($route) {
      $route->setDefault('_title_callback', InvoiceController::class . '::title');
    }
  }

}
