<?php

namespace Drupal\commerce_invoice\Access;

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

class InvoiceUserViewAccessCheck implements AccessInterface {

  /**
   * Checks access to an invoice's user view mode.
   *
   * Draft invoices are always denied as they have not yet been "confirmed".
   * Otherwise, access is delegated to entity access checks.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   */
  public function checkAccess(RouteMatchInterface $route_match, AccountInterface $account) {
    $invoice = $route_match->getParameter('commerce_invoice');
    if (!$invoice instanceof InvoiceInterface) {
      return AccessResult::neutral();
    }
    if ($invoice->getState()->getId() === 'draft') {
      return AccessResult::forbidden()->addCacheableDependency($invoice);
    }

    return $invoice->access('view', $account, TRUE);
  }

}
