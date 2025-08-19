<?php

namespace Drupal\Tests\imageapi_optimize\FunctionalJavascript;

use Drupal\Tests\image\FunctionalJavascript\ImageAdminStylesTest as OriginalImageAdminStylesTest;

/**
 * Tests creation, deletion, and editing of image styles and effects.
 *
 * @group imageapi_optimize
 */
class ImageAdminStylesTest extends OriginalImageAdminStylesTest {

 /**
  * {@inheritdoc}
  */
  protected static $modules = ['imageapi_optimize'];

}
