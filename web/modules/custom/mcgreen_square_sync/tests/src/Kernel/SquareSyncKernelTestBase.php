<?php

namespace Drupal\Tests\mcgreen_square_sync\Kernel;

use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Enables what the module's services depend on.
 */
abstract class SquareSyncKernelTestBase extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'commerce_payment',
    'commerce_reports',
    'commerce_stock',
    'key',
    'mcgreen_square_sync',
  ];

}
