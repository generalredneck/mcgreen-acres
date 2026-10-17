<?php

declare(strict_types = 1);

namespace Drupal\image_delta_formatter\OptionalPlugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_delta_formatter\Plugin\Field\FieldFormatter\ImageDeltaTrait;
use Drupal\responsive_image\Plugin\Field\FieldFormatter\ResponsiveImageFormatter;

/**
 * Plugin implementation of the 'responsive_image_delta_formatter' formatter.
 *
 * @FieldFormatter annotation is not used, to support optional loading.
 *
 * @see \image_delta_formatter_field_formatter_info_alter()
 */
class ResponsiveImageDeltaFormatter extends ResponsiveImageFormatter {

  use ImageDeltaTrait;

}
