<?php

namespace Drupal\simplenews_stats;

use Drupal\Core\Entity\EntityInterface;
use Drupal\simplenews\SubscriberInterface;

/**
 * Defines the interface for simplenews stats entity storage.
 */
interface SimplenewsStatsEntityStorageInterface {

  /**
   * Return the global newsletter stat from related entity (node).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity use as simplenews.
   *
   * @return \Drupal\simplenews_stats\SimplenewsStatsInterface|null
   *   The simplenews stats entity.
   */
  public function getFromRelatedEntity(EntityInterface $entity): ?SimplenewsStatsInterface;

  /**
   * Create an entity from subscriber  and the related entity.
   *
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The simplenews subscriber.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity use as simplenews.
   *
   * @return \Drupal\simplenews_stats\SimplenewsStatsInterface
   *   The simplenews stats entity.
   */
  public function createFromSubscriberAndEntity(SubscriberInterface $subscriber, EntityInterface $entity): SimplenewsStatsInterface;

}
