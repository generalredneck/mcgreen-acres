<?php

namespace Drupal\paragraphs_edit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Class ParagraphLineageInspector contains methods to get paragraph parents.
 */
class ParagraphLineageInspector {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Construct a new ParagraphLineageInspector object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Provides an interface for entity type managers.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets the root parent of this paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph data.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   Root parent.
   */
  public function getRootParent(ParagraphInterface $paragraph) {
    $root_parent = $paragraph->getParentEntity();
    while ($root_parent instanceof ParagraphInterface) {
      $root_parent = $root_parent->getParentEntity();
    }
    return $root_parent;
  }

  /**
   * Gets the field the paragraph is referenced from.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph data.
   *
   * @return \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList|null
   *   Parent field.
   */
  public function getParentField(ParagraphInterface $paragraph) {
    $parent = $paragraph->getParentEntity();

    if (!$parent) {
      return NULL;
    }

    return $parent->get($paragraph->get('parent_field_name')->value);
  }

  /**
   * Gets the field item the paragraph is referenced from.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph data.
   * @param \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList|null $parent_field
   *   Parent field.
   *
   * @return \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem|null
   *   Field item.
   */
  public function getParentFieldItem(ParagraphInterface $paragraph, ?EntityReferenceRevisionsFieldItemList $parent_field = NULL) {
    if (!$parent_field) {
      $parent_field = $this->getParentField($paragraph);
    }

    return $this->findParentFieldItem($paragraph, $parent_field);
  }

  /**
   * Finds the field item the paragraph is referenced from.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph data.
   * @param \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $field
   *   Field item.
   *
   * @return \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem|null
   *   Referenced field item.
   */
  protected function findParentFieldItem(ParagraphInterface $paragraph, EntityReferenceRevisionsFieldItemList $field) {
    $paragraph_id = $paragraph->id();
    $paragraph_revision_id = $paragraph->getRevisionId();

    foreach ($field as $item) {
      if (
        $item->target_id == $paragraph_id &&
        $item->target_revision_id == $paragraph_revision_id
      ) {
        return $item;
      }
    }

    return NULL;
  }

  /**
   * Builds a string representation of a paragraph's lineage.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph whose lineage to return as a string.
   *
   * @return string
   *   A string representation of the paragraph's lineage.
   */
  public function getLineageString(ParagraphInterface $paragraph) {
    $string = '';

    do {
      $parent = $paragraph->getParentEntity();
      if (!$parent) {
        break;
      }

      $parent_field = $this->getParentField($paragraph);
      $parent_field_label = $parent_field->getFieldDefinition()->getLabel();
      $parent_field_item = $this->getParentFieldItem($paragraph, $parent_field);
      $parent_field_delta = $parent_field_item ? $parent_field_item->getName() + 1 : 1;
      $bundle_label = $this->entityTypeManager
        ->getStorage('paragraphs_type')
        ->load($paragraph->bundle())
        ->label();

      $string = ' > ' . $parent_field_label . ' #' . $parent_field_delta . ' (' . $bundle_label . ')' . $string;

      $paragraph = $parent;
    } while ($parent instanceof ParagraphInterface);

    if ($parent) {
      return $parent->label() . $string;
    }
    return (string) $this->t('Orphan paragraph');
  }

}
