<?php

namespace Drupal\mcgreen_acres_store\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\commerce_order\Plugin\EntityReferenceSelection\UserSearch;

/**
 * Swaps in for commerce_order's "commerce:user" plugin to also match names.
 *
 * See mcgreen_acres_store_entity_reference_selection_alter(). Matches
 * against the customer's first/last name, taken from the "Real Name" the
 * site builds from the default 'customer' profile's billing address
 * (given_name/family_name), rather than just the account name or email.
 */
class UserSearchWithRealName extends UserSearch {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = DefaultSelection::buildEntityQuery($match, $match_operator);
    $configuration = $this->getConfiguration();
    if (!$configuration['include_anonymous']) {
      $query->condition('uid', 0, '<>');
    }

    if (isset($match)) {
      $group = $query->orConditionGroup()
        ->condition('name', $match, $match_operator)
        ->condition('mail', $match, $match_operator);
      $real_name_uids = $this->getUidsMatchingRealName($match, $match_operator);
      if ($real_name_uids) {
        $group->condition('uid', $real_name_uids, 'IN');
      }
      $query->condition($group);
    }

    if (!empty($configuration['filter']['role'])) {
      $query->condition('roles', $configuration['filter']['role'], 'IN');
    }

    if (!$this->currentUser->hasPermission('administer users')) {
      $query->condition('status', 1);
    }

    return $query;
  }

  /**
   * Finds uids whose default 'customer' profile's Real Name matches.
   *
   * The site's Real Name (see custom_user_tokens.settings and the People
   * admin view) is "[given_name] [family_name]" from the default 'customer'
   * profile's address field, so matching first, last, or "First Last"
   * requires comparing against the concatenated value, not each column
   * separately.
   *
   * @return int[]
   *   Matching user IDs.
   */
  protected function getUidsMatchingRealName($match, $match_operator) {
    $query = $this->connection->select('profile', 'p');
    $query->innerJoin('profile__address', 'a', 'a.entity_id = p.profile_id AND a.deleted = 0');
    $query->condition('p.type', 'customer');
    $query->condition('p.is_default', 1);
    $query->addField('p', 'uid');

    $concat = "CONCAT_WS(' ', a.address_given_name, a.address_family_name)";
    if ($match_operator === 'STARTS_WITH') {
      $query->where("$concat LIKE :match", [':match' => $this->connection->escapeLike($match) . '%']);
    }
    else {
      $query->where("$concat LIKE :match", [':match' => '%' . $this->connection->escapeLike($match) . '%']);
    }

    return $query->execute()->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->getConfiguration()['target_type'];

    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    $real_names = $this->getRealNames($result);

    $options = [];
    $entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $label = $entity->getAccountName() . ' <' . $entity->getEmail() . '>';
      if (!empty($real_names[$entity_id])) {
        $label .= ' - ' . $real_names[$entity_id];
      }
      $options[$bundle][$entity_id] = Html::escape($label);
    }

    return $options;
  }

  /**
   * Builds a real name for each uid's default 'customer' profile.
   *
   * The name is "[given_name] [family_name]".
   *
   * @param int[] $uids
   *   User IDs to look up.
   *
   * @return string[]
   *   Real names keyed by uid, omitting uids with no default customer
   *   profile/address.
   */
  protected function getRealNames(array $uids) {
    $query = $this->connection->select('profile', 'p');
    $query->innerJoin('profile__address', 'a', 'a.entity_id = p.profile_id AND a.deleted = 0');
    $query->condition('p.type', 'customer');
    $query->condition('p.is_default', 1);
    $query->condition('p.uid', $uids, 'IN');
    $query->addField('p', 'uid');
    $query->addExpression("CONCAT_WS(' ', a.address_given_name, a.address_family_name)", 'real_name');

    return $query->execute()->fetchAllKeyed();
  }

}
