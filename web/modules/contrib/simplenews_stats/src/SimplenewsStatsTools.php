<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Simplenews stats tools.
 *
 * @package Drupal\simplenews_stats
 */
class SimplenewsStatsTools {

  use StringTranslationTrait;

  public function __construct(
    protected EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * Returns the entity label.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that need a label.
   * @param bool $with_entity_type
   *   Add the entity type in the label.
   *
   * @return string
   *   The generated label.
   */
  public function getEntityLabel(EntityInterface $entity, bool $with_entity_type = FALSE): string {
    // Set the entity in the correct language for display.
    $entity = $this->entityRepository->getTranslationFromContext($entity);

    $label = ($with_entity_type) ? $entity->getEntityType()->getLabel() . ' | ' : '';

    // Use the special view label, since some entities allow the label to be
    // viewed, even if the entity is not allowed to be viewed.
    $label .= ($entity->access('view label')) ? $entity->label() : $this->t('- Restricted access -');

    if ($with_entity_type) {
      $label .= " ({$entity->getEntityTypeId()}|{$entity->id()})";
    }
    else {
      $label .= " ({$entity->id()})";
    }

    // Labels containing commas or quotes must be wrapped in quotes.
    return Tags::encode($label);
  }

}
