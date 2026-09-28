<?php

declare(strict_types = 1);

namespace Drupal\image_delta_formatter\OptionalPlugin\Field\FieldFormatter;

use Drupal\image_delta_formatter\Plugin\Field\FieldFormatter\ImageDeltaTrait;
use Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter;

/**
 * Plugin implementation of the 'image_delta_formatter_media' formatter.
 *
 * @FieldFormatter annotation is not used, to support optional loading.
 *
 * @see \image_delta_formatter_field_formatter_info_alter()
 */
class MediaDeltaFormatter extends MediaThumbnailFormatter {

  use ImageDeltaTrait;

}
