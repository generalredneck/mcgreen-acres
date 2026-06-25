<?php

declare(strict_types=1);

namespace Drupal\paragraphs_ee\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\paragraphs_ee\Entity\ParagraphsCategory;

/**
 * Hook implementations related to forms.
 */
class FormHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new FormHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {

  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   *
   * @phpstan-param array{paragraphs_categories: array<string, mixed>, '#entity_builders': string[]} $form
   *   The form.
   */
  #[Hook('form_paragraphs_type_form_alter')]
  function paragraphsTypeFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\paragraphs\ParagraphsTypeInterface $paragraph */
    $paragraph = $form_object->getEntity();

    /** @var \Drupal\paragraphs_ee\ParagraphsCategoryInterface[] $categories */
    $categories = $this->entityTypeManager->getStorage('paragraphs_category')
      ->loadMultiple();
    // Sort the entities using the entity class's sort() method.
    // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
    uasort($categories, [ParagraphsCategory::class, 'sort']);

    $form['paragraphs_categories'] = [
      '#type' => 'checkboxes',
      '#options' => array_combine(array_column($categories, 'id'), array_column($categories, 'label')),
      '#title' => $this->t('Paragraphs categories'),
      '#description' => $this->t('Select all categories the paragraph applies to.'),
      '#default_value' => $paragraph->getThirdPartySetting('paragraphs_ee', 'paragraphs_categories', []),
    ];

    $form['#entity_builders'][] = [$this, 'paragraphsTypeFormBuilder'];
  }

/**
 * Entity builder for the paragraphs_type configuration entity.
 *
 * @param array $form
 *   The form.
 */
public function paragraphsTypeFormBuilder(string $entity_type, ParagraphsTypeInterface $paragraph, array &$form, FormStateInterface $form_state): void {
  /** @var array<string, string> $categories */
  $categories = $form_state->getValue('paragraphs_categories', []);
  if (count($categories) > 0) {
    $paragraph->setThirdPartySetting('paragraphs_ee', 'paragraphs_categories', array_filter($categories));
    return;
  }

  // Remove setting.
  $paragraph->unsetThirdPartySetting('paragraphs_ee', 'paragraphs_categories');
}

}
