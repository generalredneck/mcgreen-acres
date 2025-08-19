<?php

namespace Drupal\Tests\imageapi_optimize\Functional;

use Drupal\Tests\image\Functional\ImageFieldValidateTest as OriginalImageFieldValidateTest;

/**
 * Tests validation functions such as min/max resolution.
 *
 * @group imageapi_optimize
 */
class ImageFieldValidateTest extends OriginalImageFieldValidateTest {

 /**
  * {@inheritdoc}
  */
  protected static $modules = ['imageapi_optimize'];

}
