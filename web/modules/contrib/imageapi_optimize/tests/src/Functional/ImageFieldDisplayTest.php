<?php

namespace Drupal\Tests\imageapi_optimize\Functional;

use Drupal\Tests\image\Functional\ImageFieldDisplayTest as OriginalImageFieldDisplayTest;

/**
 * Tests the display of image fields.
 *
 * @group imageapi_optimize
 */
class ImageFieldDisplayTest extends OriginalImageFieldDisplayTest {

 /**
  * {@inheritdoc}
  */
  protected static $modules = ['imageapi_optimize'];

}
