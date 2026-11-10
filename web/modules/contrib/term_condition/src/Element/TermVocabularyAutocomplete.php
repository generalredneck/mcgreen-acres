<?php

namespace Drupal\term_condition\Element;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\taxonomy\TermInterface;

/**
 * Provides an entity autocomplete form element with vocabulary suggestions.
 *
 * Behaves exactly like the core 'entity_autocomplete' element, except
 * autocomplete suggestions are routed through term_condition's own
 * controller so taxonomy term matches can include their vocabulary name, and
 * taxonomy term default values display their vocabulary name too.
 */
#[FormElement('term_condition_entity_autocomplete')]
class TermVocabularyAutocomplete extends EntityAutocomplete {

  /**
   * {@inheritdoc}
   */
  public static function processEntityAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $element = parent::processEntityAutocomplete($element, $form_state, $complete_form);
    $element['#autocomplete_route_name'] = 'term_condition.taxonomy_term_autocomplete';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function getEntityLabels(array $entities) {
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');
    $vocabulary_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary');

    $entity_labels = [];
    foreach ($entities as $entity) {
      // Set the entity in the correct language for display.
      $entity = $entity_repository->getTranslationFromContext($entity);

      // Use the special view label, since some entities allow the label to be
      // viewed, even if the entity is not allowed to be viewed.
      $label = ($entity->access('view label')) ? $entity->label() : t('- Restricted access -');

      // Include the vocabulary name so terms sharing a label across
      // vocabularies remain distinguishable.
      if ($entity instanceof TermInterface && ($vocabulary = $vocabulary_storage->load($entity->bundle()))) {
        $label .= ' [' . $vocabulary->label() . ']';
      }

      // Take into account "autocreated" entities.
      if (!$entity->isNew()) {
        $label .= ' (' . $entity->id() . ')';
      }

      // Labels containing commas or quotes must be wrapped in quotes.
      $entity_labels[] = Tags::encode($label);
    }

    return implode(', ', $entity_labels);
  }

}
