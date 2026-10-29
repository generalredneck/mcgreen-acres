<?php

declare(strict_types = 1);

namespace Drupal\image_delta_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'image_delta_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "image_delta_formatter",
 *   label = @Translation("Image delta"),
 *   description = @Translation("Display specific deltas of an image field."),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
#[FieldFormatter(
  id: 'image_delta_formatter',
  label: new TranslatableMarkup('Image delta'),
  description: new TranslatableMarkup('Display specific deltas of an image field.'),
  field_types: [
    'image',
  ],
)]
class ImageDeltaFormatter extends ImageFormatter {

  use ImageDeltaTrait;

}
