<?php

namespace Drupal\drimage\Controller;

/**
 * Alters the image style entity listing, excluding drimage image styles.
 *
 * @todo Replace getEntityIds with getEntityListQuery once this module has a minimum core version of 10.1.
 * @todo Replace this with an event or hook once #3221351 lands.
 *
 * @see \Drupal\image\ImageStyleListBuilder
 * @see https://www.drupal.org/project/drupal/issues/3361730
 * @see https://www.drupal.org/project/drupal/issues/3221351
 */
trait ImageStyleListBuilderTrait {

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    // This separate query is necessary because config entity queries don't
    // support a NOT STARTS_WITH operator.
    $drimageStyleIds = $this->getStorage()->getQuery()
      ->accessCheck(FALSE)
      ->condition($this->entityType->getKey('id'), 'drimage_', 'STARTS_WITH')
      ->execute();

    $query = $this->getStorage()->getQuery()
      ->accessCheck()
      ->condition($this->entityType->getKey('id'), $drimageStyleIds, 'NOT IN')
      ->sort($this->entityType->getKey('id'));

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

}
