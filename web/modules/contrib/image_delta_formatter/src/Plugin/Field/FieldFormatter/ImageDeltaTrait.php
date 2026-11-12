<?php

declare(strict_types=1);

namespace Drupal\image_delta_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Common implementation for the image and media delta formatters. formatter.
 */
trait ImageDeltaTrait {

  /**
   * Provide default formatter settings.
   *
   * @return array<string,mixed>
   *   The formatter defaults.
   */
  public static function defaultSettings(): array {
    return [
        'deltas' => [],
        'deltas_reversed' => FALSE,
      ] + parent::defaultSettings();
  }

  /**
   * Build the configuration form for the formatter.
   *
   * @param array<string,mixed> $form
   *   The existing form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array<string,mixed>
   *   The extended form.
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);

    $deltas = $this->getSetting('deltas');
    if (is_array($deltas)) {
      $deltas = implode(', ', $deltas);
    }
    $element['deltas'] = [
      '#default_value' => $deltas,
      '#description' => $this->t('Enter a delta, or a comma-separated list of deltas that should be shown. For example: 0, 1, 4.'),
      '#element_validate' => [[$this, 'validateDeltas']],
      '#required' => TRUE,
      '#size' => 10,
      '#title' => $this->t('Delta'),
      '#type' => 'textfield',
      '#weight' => -20,
    ];
    $element['deltas_reversed'] = [
      '#default_value' => $this->getSetting('deltas_reversed'),
      '#description' => $this->t('Start from the last values.'),
      '#title' => $this->t('Reversed'),
      '#type' => 'checkbox',
      '#weight' => -10,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $settings = $this->getSettings();
    $summary = parent::settingsSummary();

    ['deltas' => $deltas, 'deltas_reversed' => $reversed] = $settings;
    // Handle legacy config format.
    if (is_scalar($deltas)) {
      $deltas = $this->deltasFromString($deltas);
    }
    $count = count($deltas);
    $args = [
      '@deltas' => trim(implode(', ', $deltas)),
    ];
    $delta_summary = $reversed
      ? $this->formatPlural($count,
        'Delta: @deltas (reversed, no effect).', 'Deltas: @deltas (reversed).',
        $args)
      : $this->formatPlural($count,
        'Delta: @deltas', 'Deltas: @deltas',
        $args);

    $summary[] = $delta_summary;

    return $summary;
  }

  /**
   * Convert a deltas string to a clean deltas array.
   *
   * @param scalar $s
   *   A scalar describing the list of deltas.
   *
   * @return int[]
   *   The deduplicated, unsorted, integer deltas found in the source string.
   */
  protected static function deltasFromString($s): array {
    $split = explode(',', '' . $s);
    $trimmed = array_map(fn($v) => (int) trim($v), $split);
    $unique = array_unique($trimmed);
    // Avoid non-sequential index keys that could result from input like "0,,1".
    $normalized = array_values($unique);
    return $normalized;
  }

  /**
   * Element validate handler for deltas.
   *
   * @param array<string,mixed> $element
   *   The deltas elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string,mixed> $complete_form
   *   The complete form.
   */
  public function validateDeltas(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    /** @var string $value */
    $value = $element['#value'] ?? '';
    $deltas = $this->deltasFromString($value);
    $form_state->setValueForElement($element, $deltas);
  }

  /**
   * Returns the referenced entities for display.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items
   *   The image or media entity items.
   * @param string $langcode
   *   The language for which to display them.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The referenced entities.
   *
   * @see \Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode): array {
    $files = parent::getEntitiesToView($items, $langcode);
    // Prepare an array of selected deltas from the entered string.
    /** @var string|array $rawDeltas */
    $rawDeltas = $this->getSetting('deltas');
    if (is_scalar($rawDeltas)) {
      $rawDeltas = '' . $rawDeltas;
      if (mb_strpos($rawDeltas, ',')) {
        $deltas = explode(',', $rawDeltas);
        $deltas = array_map('trim', $deltas);
        $deltas = array_map('intval', $deltas);
      }
      else {
        /** @var int $delta */
        $delta = (int) trim($rawDeltas);
        $deltas = [$delta];
      }
    }
    else {
      $deltas = $rawDeltas;
    }

    foreach (array_keys($files) as $delta) {
      if (!in_array($delta, $deltas)) {
        unset($files[$delta]);
      }
    }

    // Reverse the items if needed.
    if ($this->getSetting('deltas_reversed')) {
      $files = array_reverse($files);
    }

    return $files;
  }

}
