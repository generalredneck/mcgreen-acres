<?php

namespace Drupal\commerce_invoice\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines an access checker for the Order invoices route.
 */
class InvoiceOrderAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new InvoiceOrderAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks access to the Order invoices route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    $invoice_type_storage = $this->entityTypeManager->getStorage('commerce_invoice_type');

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $route_match->getParameter('commerce_order');
    /** @var \Drupal\commerce_invoice\Entity\InvoiceTypeInterface $invoice_type */
    $invoice_type = $route_match->getParameter('commerce_invoice_type') ?: $invoice_type_storage->load('default');

    $access = AccessResult::allowedIfHasPermission($account, 'administer commerce_invoice')
      ->mergeCacheMaxAge(0);

    // Custom requirement for the invoice generate form.
    if ($route->hasRequirement('_invoice_generate_form_access')) {
      $disallowed_order_states = ['draft'];

      // Credit memos (refunds) can be created for a canceled order.
      if ($invoice_type->id() !== 'credit_memo') {
        $disallowed_order_states[] = 'canceled';
      }
      if (in_array($order->getState()->getId(), $disallowed_order_states, TRUE) || !$order->getTotalPrice()) {
        return AccessResult::forbidden()->mergeCacheMaxAge(0);
      }

      $result = $this->entityTypeManager->getStorage('commerce_invoice')->getAggregateQuery()
        ->condition('type', $invoice_type->id(), '=')
        ->condition('orders', [$order->id()], 'IN')
        ->conditionAggregate('total_price.number', 'SUM', $order->getTotalPrice()->getNumber(), '>=')
        ->accessCheck(FALSE)
        ->execute();

      // Do not allow access to the invoice generate form if the total price sum
      // of the invoices that reference this order match or exceed its total
      // price.
      if (!empty($result)) {
        return AccessResult::forbidden()->mergeCacheMaxAge(0);
      }

      // The invoice generator service needs a store.
      $order_requirements = !empty($order->getStoreId());
      $access->andIf(AccessResult::allowedIf($order_requirements));
    }

    return $access;
  }

}
