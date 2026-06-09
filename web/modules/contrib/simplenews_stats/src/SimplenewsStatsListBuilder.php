<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the simplenews stats entity type.
 */
class SimplenewsStatsListBuilder extends EntityListBuilder {

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['table'] = parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Title');
    $header['views'] = $this->t('Views');
    $header['clicks'] = $this->t('Clicks');
    $header['total_mails'] = $this->t('Total sent');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\simplenews_stats\Entity\SimplenewsStats $entity */

    $row['title'] = $entity->toLink()->toString();
    $row['views'] = $entity->getViews();
    $row['clicks'] = $entity->getClicks();
    $row['total_mails'] = $entity->getTotalMails();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    // Add view action.
    if ($entity->access('view') && $entity->hasLinkTemplate('canonical')) {
      $operations['edit'] = [
        'title' => $this->t('View'),
        'weight' => -60,
        'url' => $this->ensureDestination($entity->toUrl()),
      ];
    }

    $destination = $this->getDestinationArray();
    foreach ($operations as $key => $operation) {
      $operations[$key]['query'] = $destination;
    }
    return $operations;
  }

}
