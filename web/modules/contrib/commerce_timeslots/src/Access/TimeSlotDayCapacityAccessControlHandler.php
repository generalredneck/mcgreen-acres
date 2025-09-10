<?php

namespace Drupal\commerce_timeslots\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Commerce timeSlot day capacity entity.
 */
class TimeSlotDayCapacityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   *
   * Link the activities to the permissions. checkAccess is called with the
   * $operation as defined in the routing.yml file.
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view commerce timeslot day capacity entity');

      case 'edit':
      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit commerce timeslot day capacity entity');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete commerce timeslot day capacity entity');
    }
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   *
   * Separate from the checkAccess because the entity does not yet exist, it
   * will be created during the 'add' process.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $result = AccessResult::allowedIfHasPermissions(
      $account,
      ['add commerce timeslot day capacity entity'],
      'OR'
    );
    return $result;
  }

}
