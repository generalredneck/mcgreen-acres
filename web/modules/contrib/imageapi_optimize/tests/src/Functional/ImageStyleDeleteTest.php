<?php

namespace Drupal\Tests\imageapi_optimize\Functional;

use Drupal\Tests\image\Functional\ImageStyleDeleteTest as OriginalImageStyleDeleteTest;

/**
 * Tests image style deletion using the UI.
 *
 * @group imageapi_optimize
 */
class ImageStyleDeleteTest extends OriginalImageStyleDeleteTest {

 /**
  * {@inheritdoc}
  */
  protected static $modules = ['imageapi_optimize'];

}
