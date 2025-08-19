<?php

namespace Drupal\Tests\imageapi_optimize\Kernel;

use Drupal\Tests\image\Kernel\FileMoveTest as OriginalFileMoveTest;

/**
 * Tests the file move function for images and image styles.
 *
 * @group imageapi_optimize
 */
class FileMoveTest extends OriginalFileMoveTest {

 /**
  * {@inheritdoc}
  */
  protected static $modules = ['imageapi_optimize'];

}
