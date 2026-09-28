<?php

namespace Drupal\drimage_improved\Controller;

use Drupal\image\ImageStyleListBuilder as ImageStyleListBuilderBase;

/**
 * Alters the default image style entity listing.
 */
class ImageStyleListBuilder extends ImageStyleListBuilderBase {

  use ImageStyleListBuilderTrait;

}
