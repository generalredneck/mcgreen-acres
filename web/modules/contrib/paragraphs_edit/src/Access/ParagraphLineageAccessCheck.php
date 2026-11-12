<?php

namespace Drupal\paragraphs_edit\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_edit\ParagraphLineageInspector;

/**
 * Checks that the root parent in the path is the paragraph's root parent.
 */
class ParagraphLineageAccessCheck implements AccessInterface {

  /**
   * The lineage inspector.
   *
   * @var \Drupal\paragraphs_edit\ParagraphLineageInspector
   */
  protected $lineageInspector;

  /**
   * Constructs a ParagraphLineageAccessCheck object.
   *
   * @param \Drupal\paragraphs_edit\ParagraphLineageInspector $lineage_inspector
   *   The lineage inspector.
   */
  public function __construct(ParagraphLineageInspector $lineage_inspector) {
    $this->lineageInspector = $lineage_inspector;
  }

  /**
   * Checks that the paragraph belongs to the root parent in the path.
   *
   * @param \Drupal\Core\Entity\EntityInterface $root_parent
   *   The root parent from the path.
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph from the path.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Allowed when the root parent in the path is the paragraph's root parent.
   */
  public function access(EntityInterface $root_parent, ParagraphInterface $paragraph): AccessResultInterface {
    $actual_root_parent = $this->lineageInspector->getRootParent($paragraph);

    return AccessResult::allowedIf(
      $actual_root_parent
      && $actual_root_parent->getEntityTypeId() === $root_parent->getEntityTypeId()
      && (string) $actual_root_parent->id() === (string) $root_parent->id()
    )
      ->addCacheableDependency($paragraph)
      ->addCacheableDependency($root_parent);
  }

}
