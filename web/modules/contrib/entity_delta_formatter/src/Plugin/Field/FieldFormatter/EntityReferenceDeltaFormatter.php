<?php

namespace Drupal\entity_delta_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * The Entity Reference Delta Formatter.
 *
 * Provides the ability to select certain entities from the entity reference
 * field to display.
 *
 * @FieldFormatter(
 *   id = "entity_reference_delta_formatter",
 *   label = @Translation("Rendered entities by delta"),
 *   description = @Translation("Display the selected referenced entities rendered by entity_view()."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceDeltaFormatter extends EntityReferenceEntityFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'deltas' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    // Add the text field. Specifies a pattern that allows any number of the
    // following blocks separated by commas. Allows whitespace between numbers
    // and underscores and between numbers and commas.
    // @code
    // 1
    // -1
    // 1 _ 1
    // -1 _ 1
    // 1 _ -1
    // -1 _ -1
    // @endcode
    $form['deltas'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deltas'),
      '#description' => $this->t('Describe the entity deltas to display, separated by commas. Use "_" to select ranges of entities. Use "-" to select deltas from the end of the list.'),
      '#default_value' => $this->getSetting('deltas'),
      '#pattern' => '(?:(?:\s*-?\d+\s*(?:_\s*-?\d+\s*)?),)*(?:\s*-?\d+\s*(?:_\s*-?\d+\s*)?)',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    $deltas = $this->getSetting('deltas');
    if (!empty($deltas)) {
      $summary[] = $this->t('Deltas: @deltas', ['@deltas' => $deltas]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $selected_items = clone $items;

    $this->filterItemsByDelta($selected_items);

    return parent::viewElements($selected_items, $langcode);
  }

  /**
   * Filters the item list by delta.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The item list.
   */
  public function filterItemsByDelta(FieldItemListInterface $items): FieldItemListInterface {
    // Get the deltas as an array of ranges.
    $deltas = $this->getSetting('deltas');

    // Make no changes if there are no deltas specified.
    if (empty($deltas)) {
      return $items;
    }

    // Strip whitespace and non-accepted characters.
    $deltas = preg_replace('/(\s|[^(\d|,|\-|_)])/', '', $deltas);

    // Change repeated dashes and underscores to single ones.
    $deltas = preg_replace('/_{2,}/', '_', $deltas);
    $deltas = preg_replace('/\-{2,}/', '-', $deltas);

    // Remove dashes not followed by a number and underscores not between two
    // numbers.
    $deltas = preg_replace('/\-(?!\d)/', '', $deltas);
    $deltas = preg_replace('/((?<!\d)_|_(?!(\-|\d)))/', '', $deltas);

    // Get the selections as an array.
    $deltas = explode(',', $deltas);

    // Get the count of items.
    $count = $items->count();

    // Preserve the chosen deltas as an array of indices.
    $preserve = [];
    foreach ($deltas as $range) {
      if (strpos($range, '_') !== FALSE) {
        // If the range is a range of values, get the start and end.
        [$start, $end] = explode('_', $range);

        // Normalize the start and end.
        $start = $this->deltaNormalize($start, $count);
        $end = $this->deltaNormalize($end, $count);

        // If the start is greater than the end, flip them.
        if ($start > $end) {
          $hold = $start;
          $start = $end;
          $end = $hold;
          unset($hold);
        }

        // Preserve the indices.
        while ($start <= $end) {
          if (!in_array($start, $preserve)) {
            $preserve[] = $start;
          }
          $start++;
        }
      }
      else {
        // If the range is a single value, normalize and preserve the index.
        $range = $this->deltaNormalize($range, $count);
        if (!in_array($range, $preserve)) {
          $preserve[] = $range;
        }
      }
    }

    // Remove the items not being preserved. Count indexes backwards to prevent
    // changing future indexes.
    for ($index = $count - 1; $index >= 0; $index--) {
      if (!in_array($index, $preserve)) {
        $items->removeItem($index);
      }
    }

    return $items;
  }

  /**
   * Adjust deltas to match list positions.
   *
   * Converts negative deltas into positive ones, corrects off by one errors for
   * positive deltas, and prevents out of range deltas.
   *
   * @param int $delta
   *   The delta.
   * @param int $count
   *   The number of items.
   *
   * @return int
   *   The delta.
   */
  protected function deltaNormalize($delta, $count): int {
    // Process the deltas into their correct positive values.
    if (strpos($delta, '-') === 0) {
      $delta = $count + $delta;
    }
    else {
      $delta--;
    }

    // Do not permit out of range deltas.
    if ($delta > $count - 1) {
      $delta = $count - 1;
    }
    elseif ($delta < 0) {
      $delta = 0;
    }

    return $delta;
  }

}
