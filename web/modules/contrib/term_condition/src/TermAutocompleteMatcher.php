<?php

namespace Drupal\term_condition;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityAutocompleteMatcherInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Matcher for entity autocomplete that appends the vocabulary name.
 *
 * Mirrors \Drupal\Core\Entity\EntityAutocompleteMatcher, but for
 * taxonomy_term matches includes the term's vocabulary label alongside the
 * term label, e.g. "Term name [Vocabulary name] (5)".
 */
class TermAutocompleteMatcher implements EntityAutocompleteMatcherInterface {

  public function __construct(
    protected SelectionPluginManagerInterface $selectionManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getMatches($target_type, $selection_handler, $selection_settings, $string = '') {
    $matches = [];

    $options = $selection_settings + [
      'target_type' => $target_type,
      'handler' => $selection_handler,
    ];
    $handler = $this->selectionManager->getInstance($options);

    if (isset($string)) {
      // Get an array of matching entities.
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $match_limit = isset($selection_settings['match_limit']) ? (int) $selection_settings['match_limit'] : 10;
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, $match_limit);

      $vocabulary_storage = $target_type === 'taxonomy_term' ? $this->entityTypeManager->getStorage('taxonomy_vocabulary') : NULL;

      // Loop through the entities and convert them into autocomplete output.
      // Entities are grouped by bundle; for taxonomy_term that bundle is the
      // vocabulary machine name.
      foreach ($entity_labels as $bundle => $values) {
        $vocabulary_label = $bundle;
        if ($vocabulary_storage && ($vocabulary = $vocabulary_storage->load($bundle))) {
          $vocabulary_label = $vocabulary->label();
        }

        foreach ($values as $entity_id => $label) {
          $display_label = $vocabulary_storage ? "$label [$vocabulary_label]" : $label;

          $key = "$display_label ($entity_id)";
          // Strip things like starting/trailing white spaces, line breaks and
          // tags.
          $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
          // Names containing commas or quotes must be wrapped in quotes.
          $key = Tags::encode($key);
          $matches[] = ['value' => $key, 'label' => $display_label];
        }
      }
    }

    return $matches;
  }

}
