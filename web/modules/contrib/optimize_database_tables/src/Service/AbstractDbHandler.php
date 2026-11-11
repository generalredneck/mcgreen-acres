<?php

declare(strict_types=1);

namespace Drupal\optimize_database_tables\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Provides shared, driver-agnostic methods for database handler services.
 *
 * Concrete subclasses must implement the driver-specific methods declared
 * in DbHandlerInterface. This abstract class holds only logic that is
 * identical across all supported drivers.
 */
abstract class AbstractDbHandler implements DbHandlerInterface {

  /**
   * Constructs a new AbstractDbHandler.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   */
  public function __construct(
    protected readonly Connection $database,
    protected readonly LoggerChannelFactoryInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @param array<int|string, string> $tables
   *   A list of table names (strings).
   */
  public function getTotalSizeBytes(array $tables): int {
    $total = 0;
    foreach ($tables as $table) {
      $total += $this->getTableSizeBytes($table);
    }
    return $total;
  }

}
