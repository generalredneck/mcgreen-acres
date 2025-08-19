<?php

namespace Drupal\Tests\imageapi_optimize\Functional;

use Drupal\Tests\image\Functional\ImageOnTranslatedEntityTest as OriginalImageOnTranslatedEntityTest;

/**
 * Uploads images to translated nodes.
 *
 * @group imageapi_optimize
 */
class ImageOnTranslatedEntityTest extends OriginalImageOnTranslatedEntityTest {

 /**
  * {@inheritdoc}
  */
  protected static $modules = ['imageapi_optimize'];

}
