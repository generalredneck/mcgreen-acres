<?php

namespace Drupal\interval\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a default formatter class for interval fields.
 *
 * @FieldFormatter(
 *   id = "interval_raw",
 *   label = @Translation("Raw value"),
 *   field_types = {
 *     "interval"
 *   },
 * )
 */
#[FieldFormatter(
  id: 'interval_raw',
  label: new TranslatableMarkup('Raw value'),
  field_types: ['interval'],
)]
class IntervalFormatterRaw extends IntervalFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#markup' => new HtmlEscapedText($this->formatInterval($item)),
      ];
    }
    return $element;
  }

}
