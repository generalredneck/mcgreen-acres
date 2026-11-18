<?php

declare(strict_types=1);

namespace Drupal\paragraphs_ee_sets\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for theming.
 */
class ThemeHooks {

  use StringTranslationTrait;

  /**
   * Override variables used in paragraphs-add-dialog--categorized.html.twig.
   *
   * @param array<string, mixed> $variables
   *   An associative array containing variables used in the template.
   */
  #[Hook(
    hook: 'preprocess_paragraphs_add_dialog__categorized',
    order: Order::Last,
  )]
  public function preprocessParagraphsAddDialogCategorized(array &$variables): void {
    /** @var array<string, array<int, array<string, mixed>>> $groups */
    $groups = $variables['groups'] ?? [];
    $groups['paragraphs_sets'] = [];

    /** @var array<string, mixed> $element */
    $element = &$variables['element'];

    /** @var array<int, string> $element_children */
    $element_children = Element::children($element);
    foreach ($element_children as $key) {
      if (strpos($key, 'append_selection_button_') === 0) {
        // Buttons for the paragraph sets in the modal form.
        /** @var array<string, mixed> $button */
        $button = $element[$key];
        /** @var array<string, mixed> $button_attributes */
        $button_attributes = $button['#attributes'] ?? [];
        /** @var string $button_id */
        $button_id = $button['#id'] ?? '';
        $button_attributes['aria-describedby'] = $button_id . '--description';
        $button['#attributes'] = $button_attributes;
        $groups['paragraphs_sets'][] = $button;
      }
    }
    if (empty($groups['paragraphs_sets'])) {
      // Remove empty group.
      unset($groups['paragraphs_sets']);
    }
    else {
      // Add new button group.
      /** @var array<string, array<string, mixed>> $categories */
      $categories = $variables['categories'] ?? [];
      /** @var string $element_id */
      $element_id = $element['#id'] ?? '';
      $categories['paragraphs_sets'] = [
        'id' => Html::getUniqueId($element_id . '-category-paragraphs_sets'),
        'title' => $this->t('Paragraphs Sets', [], ['context' => 'Paragraphs EE Sets: categories']),
        'description' => '',
      ];
      $variables['categories'] = $categories;
    }
    $variables['groups'] = $groups;
  }

}
