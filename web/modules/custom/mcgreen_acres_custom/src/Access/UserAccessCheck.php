<?php

namespace Drupal\mcgreen_acres_custom\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\user\UserInterface;

/**
 * Checks access for displaying configuration translation page.
 */
class UserAccessCheck implements AccessInterface {

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\user\UserInterface $user
   *   The user account being accessed.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, UserInterface $user) {

    // Check if admin has "Administer users" permission.
    return AccessResult::allowedIfHasPermission($account, 'administer users')
        // Check if current user id = visited user id.
      ->orIf(AccessResult::allowedIf($user->id() == $account->id()));
  }

}
