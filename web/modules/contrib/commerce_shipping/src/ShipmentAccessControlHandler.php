<?php

namespace Drupal\commerce_shipping;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler as CoreEntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;

/**
 * Provides an access control handler for shipments.
 *
 * Shipments are always managed in the scope of their parent (the order),
 * so they have a simplified permission set, and rely on parent access
 * when possible:
 * - An shipment can be viewed if the parent order can be viewed.
 * - The confirmation of a shipment can be resent if the receipt of the
 *   parent order can be resent.
 * - An shipment can be created, updated or deleted if the user has the
 *   "manage $bundle shipments" permission.
 * - Restricted fields (state, items, amounts, etc.) can only be edited
 *   with the "manage $bundle shipments" permission.
 *
 * The "administer commerce_shipment" permission is also respected.
 */
class ShipmentAccessControlHandler extends CoreEntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    assert($entity instanceof ShipmentInterface);
    $order = $entity->getOrder();
    if (!$order) {
      // The shipment is malformed.
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    $bundle = $entity->bundle();
    $result = AccessResult::allowedIfHasPermission($account, "manage $bundle commerce_shipment");
    if ($result->isNeutral() && ($operation === 'view' || $operation === 'update')) {
      $result = $order->access($operation, $account, TRUE);
    }
    elseif ($result->isNeutral() && $operation === 'resend_confirmation') {
      $result = $order->access('resend_receipt', $account, TRUE);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL) {
    // Only users who can manage shipments or update orders through their
    // permissions can edit restricted fields. Shipment "update" access also
    // follows the order, which is granted to customers for their own cart (by
    // commerce_cart), and must not allow changing the state, items, amounts,
    // etc. (e.g. through JSON:API or REST). The checkout form only uses the
    // shipping method and the shipping profile.
    // Modules add their own fields to the "restricted_fields" entity
    // type property, in hook_entity_type_build().
    $restricted_fields = $this->entityType->get('restricted_fields') ?? [];
    if ($operation == 'edit' && in_array($field_definition->getName(), $restricted_fields, TRUE)) {
      $permissions = [$this->entityType->getAdminPermission(), 'administer commerce_order'];
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface|null $shipment */
      $shipment = $items?->getEntity();
      $bundle = $shipment ? $shipment->bundle() : $field_definition->getTargetBundle();
      if ($bundle) {
        $permissions[] = "manage $bundle commerce_shipment";
      }
      // Staff who can update the order through their permissions can edit its
      // shipments (e.g. the tracking code), like the order's "shipments" field.
      if ($order_type_id = $shipment?->getOrder()?->bundle()) {
        $permissions[] = "update $order_type_id commerce_order";
      }
      return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
    }

    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Create access depends on the "manage" permission because the full entity
    // is not passed, making it impossible to determine the parent order.
    return AccessResult::allowedIfHasPermissions($account, [
      $this->entityType->getAdminPermission(),
      "manage $entity_bundle commerce_shipment",
    ], 'OR');
  }

}
