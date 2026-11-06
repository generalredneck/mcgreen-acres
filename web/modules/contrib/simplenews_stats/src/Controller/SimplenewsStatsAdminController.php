<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\simplenews_stats\SimplenewsStatsPage;

/**
 * Admin controller for simplenews stats.
 */
class SimplenewsStatsAdminController extends ControllerBase {

  /**
   * Access callback.
   *
   * Check if the node is a simplenews and if the user has access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public static function simplenewsStatsAccess(AccountInterface $account, NodeInterface $node) {
    if ($node->hasField('simplenews_issue') &&
      !$node->get('simplenews_issue')->isEmpty()) {
      if ($account->hasPermission('access simplenews stats results')) {
        return AccessResult::allowed();
      }

      if ($account->hasPermission('access simplenews stats results editable node') && $node->access('update', $account)) {
        return AccessResult::allowed()->addCacheableDependency($node);
      }
    }

    return AccessResult::neutral();
  }

  /**
   * Stats page callback.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The node used by simplenews.
   *
   * @return array
   *   A render array representing the page content.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function stats(EntityInterface $node): array {
    return (new SimplenewsStatsPage($node))->getPage();
  }

}
