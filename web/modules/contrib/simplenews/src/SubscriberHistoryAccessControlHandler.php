<?php

namespace Drupal\simplenews;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the subscriber history entity type.
 *
 * History records are an immutable audit log: they are only ever created
 * programmatically by \Drupal\simplenews\Subscription\SubscriptionManager and
 * are never edited or deleted through the UI. Access therefore only grants
 * viewing, gated by the same permissions that protect subscriber data.
 *
 * @see \Drupal\simplenews\Entity\SubscriberHistory
 */
class SubscriberHistoryAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation === 'view') {
      return AccessResult::allowedIfHasPermissions($account, [
        'administer simplenews subscriptions',
        'view simplenews subscriptions',
      ], 'OR');
    }

    // History is append-only; nothing may update or delete it via the UI.
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::forbidden();
  }

}
