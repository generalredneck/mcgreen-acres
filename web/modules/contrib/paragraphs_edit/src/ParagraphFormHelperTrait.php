<?php

namespace Drupal\paragraphs_edit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Provides a helper for paragraph forms.
 */
trait ParagraphFormHelperTrait {

  /**
   * The lineage inspector.
   *
   * @var \Drupal\paragraphs_edit\ParagraphLineageInspector
   */
  private $lineageInspector;

  /**
   * The lineage revisioner.
   *
   * @var \Drupal\paragraphs_edit\ParagraphLineageRevisioner
   */
  private $lineageRevisioner;

  /**
   * The root parent.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $rootParent;

  /**
   * Returns the lineage inspector service.
   *
   * @return \Drupal\paragraphs_edit\ParagraphLineageInspector
   *   The lineage inspector service data.
   */
  protected function lineageInspector() {
    if (!isset($this->lineageInspector)) {
      $this->lineageInspector = \Drupal::service('paragraphs_edit.lineage.inspector');
    }
    return $this->lineageInspector;
  }

  /**
   * Returns the lineage revisioner service.
   *
   * @return \Drupal\paragraphs_edit\ParagraphLineageRevisioner|null
   *   The lineage revisioner service data.
   */
  protected function lineageRevisioner() {
    if (!isset($this->lineageRevisioner)) {
      $this->lineageRevisioner = \Drupal::service('paragraphs_edit.lineage.revisioner');
    }
    return $this->lineageRevisioner;
  }

  /**
   * {@inheritdoc}
   *
   * Overridden to store the root parent entity.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $root_parent = NULL) {
    $this->rootParent = $root_parent;

    return parent::buildForm($form, $form_state);
  }

  /**
   * Returns the root entity url of a paragraph.
   *
   * Paragraphs do not have a canonical URL, so when we need to redirect (or
   * build a cancel link) we need to navigate to their root entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity we're currently acting on.
   *
   * @return \Drupal\Core\Url
   *   The resolved root entity url.
   */
  protected function getRootEntityUrl(EntityInterface $entity): Url {
    if ($entity instanceof ParagraphInterface) {
      // Resolve the root entity if this is a paragraph and use the front page
      // as fallback, if the root could not get resolved:
      return $this->lineageInspector()->getRootParent($entity)?->toUrl() ?? Url::fromRoute('<front>');
    }
    // The entity is not a paragraph, simply returns its url:
    return $entity->toUrl();
  }

}
