<?php

namespace Drupal\interval\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a default formatter class for interval fields.
 *
 * @FieldFormatter(
 *   id = "interval_default",
 *   label = @Translation("Plain"),
 *   field_types = {
 *     "interval"
 *   },
 * )
 */
#[FieldFormatter(
  id: 'interval_default',
  label: new TranslatableMarkup('Plain'),
  field_types: ['interval'],
)]
class IntervalFormatterDefault extends IntervalFormatterBase {}
