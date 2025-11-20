<?php

declare(strict_types=1);

namespace Drupal\recipe_tracker;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Provides a list controller for the log entity type.
 */
final class LogListBuilder extends EntityListBuilder {

  protected function getEntityListQuery(): QueryInterface {
    $query = parent::getEntityListQuery();
    $query->sort('id', 'DESC');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['uid'] = $this->t('Applied by');
    $header['recipe_label'] = $this->t('Recipe');
    $header['version'] = $this->t('Version');
    $header['applied'] = $this->t('Applied');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\recipe_tracker\LogInterface $entity */
    $username_options = [
      'label' => 'hidden',
      'settings' => ['link' => $entity->get('uid')->entity->isAuthenticated()],
    ];
    $row['uid']['data'] = $entity->get('uid')->view($username_options);
    $row['recipe_label']['data'] = $entity->get('recipe_label')->view(['label' => 'hidden']);
    $row['version']['data'] = $entity->get('version')->view(['label' => 'hidden']);
    $row['applied']['data'] = $entity->get('created')->view(['label' => 'hidden']);
    return $row + parent::buildRow($entity);
  }

}
