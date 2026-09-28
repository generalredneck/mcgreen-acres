<?php

namespace Drupal\simplenews\Storage;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines the subscriber history schema handler.
 *
 * Adds indexes for the common lookups: every history record for one address
 * ordered by time (the per-subscriber history page and
 * SubscriptionManager::hasSubscribed()).
 */
class SubscriberHistoryStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    if ($base_table = $this->storage->getBaseTable()) {
      $schema[$base_table]['indexes'] += [
        'simplenews_subscriber_history__mail' => ['mail'],
        'simplenews_subscriber_history__mail_timestamp' => ['mail', 'timestamp'],
      ];
    }

    return $schema;
  }

}
