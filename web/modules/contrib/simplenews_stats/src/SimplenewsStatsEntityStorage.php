<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\simplenews\SubscriberInterface;

/**
 * Simplenews stats entity storage.
 */
class SimplenewsStatsEntityStorage extends SqlContentEntityStorage implements SimplenewsStatsEntityStorageInterface {

  /**
   * The simplenews stats item storage.
   */
  protected EntityStorageInterface $simplenewsStatsItemStorage;

  /**
   * SimplenewsStatsEntityStorage constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to be used.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache backend to be used.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeInterface $entity_type, Connection $database, EntityFieldManagerInterface $entity_field_manager, CacheBackendInterface $cache, LanguageManagerInterface $language_manager, MemoryCacheInterface $memory_cache, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type, $database, $entity_field_manager, $cache, $language_manager, $memory_cache, $entity_type_bundle_info, $entity_type_manager);

    $this->simplenewsStatsItemStorage = $this->entityTypeManager->getStorage('simplenews_stats_item');
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $entities) {
    foreach ($entities as $entity) {
      $query = $this->simplenewsStatsItemStorage->getQuery();
      $children_ids = $query->condition('entity_type', $entity->entity_type->first()
        ->getValue())
        ->condition('entity_id', $entity->entity_id->first()->getValue())
        ->accessCheck()
        ->execute();

      if (!empty($children_ids)) {
        $children = $this->simplenewsStatsItemStorage->loadMultiple($children_ids);
        $this->simplenewsStatsItemStorage->delete($children);
      }
    }

    parent::delete($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function getFromRelatedEntity(EntityInterface $entity): ?SimplenewsStatsInterface {
    $result = $this->getQuery()
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->accessCheck()
      ->execute();

    if (empty($result)) {
      return NULL;
    }

    return $this->load(reset($result));
  }

  /**
   * {@inheritdoc}
   */
  public function createFromSubscriberAndEntity(SubscriberInterface $subscriber, EntityInterface $entity): SimplenewsStatsInterface {
    $data = [
      'snid' => $subscriber->id(),
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'created' => \Drupal::time()->getRequestTime(),
    ];

    return $this->create($data);
  }

}
