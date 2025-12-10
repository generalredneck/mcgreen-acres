<?php

namespace Drupal\commerce_invoice;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\EntityAccessControlHandler;

/**
 * Controls access based on the Invoice entity permissions.
 */
class InvoiceAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $account = $this->prepareUser($account);

    if ($operation === 'resend_confirmation') {
      if ($entity->getState()->getId() == 'draft') {
        return AccessResult::forbidden()->addCacheableDependency($entity);
      }
      $operation = 'view';
      $additional_operation = 'resend_confirmation';
    }

    /** @var \Drupal\Core\Access\AccessResult $result */
    $result = parent::checkAccess($entity, $operation, $account);

    /** @var \Drupal\commerce_order\Entity\OrderInterface $entity */
    if (($operation === 'view') && $result->isNeutral()) {
      if ($account->isAuthenticated() && $account->id() === $entity->getCustomerId() && empty($additional_operation)) {
        $result = AccessResult::allowedIfHasPermissions($account, ['view own commerce_invoice']);
        $result = $result->cachePerUser()->addCacheableDependency($entity);
      }
    }

    return $result;
  }

}
