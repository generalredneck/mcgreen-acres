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
 *   id = "interval_php",
 *   label = @Translation("PHP date/time"),
 *   field_types = {
 *     "interval"
 *   },
 * )
 */
#[FieldFormatter(
  id: 'interval_php',
  label: new TranslatableMarkup('PHP date/time'),
  field_types: ['interval'],
)]
class IntervalFormatterPhp extends IntervalFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\interval\IntervalItemInterface $item */
    $element = [];
    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#markup' => new HtmlEscapedText($item->buildPHPString()),
      ];
    }
    return $element;
  }

}
