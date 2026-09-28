<?php

declare(strict_types=1);

namespace Drupal\optimize_database_tables\Service;

/**
 * Defines the contract for database handler services.
 *
 * Implementations must provide driver-specific optimisation commands,
 * table listing, and size computation in a driver-agnostic way to callers.
 */
interface DbHandlerInterface {

  /**
   * Returns the list of base tables available in the current database.
   *
   * @return array<string, string>
   *   An associative array of table names keyed by their names.
   */
  public function getTablesList(): array;

  /**
   * Optimizes a single table according to the active database driver.
   *
   * @param string $table_name
   *   The table name to optimize.
   */
  public function optimizeTable(string $table_name): void;

  /**
   * Computes the total size of a table (data + indexes) in bytes.
   *
   * @param string $table_name
   *   The table name.
   *
   * @return int
   *   The table size in bytes, or 0 if unavailable or on error.
   */
  public function getTableSizeBytes(string $table_name): int;

  /**
   * Computes the total size in bytes for a list of tables.
   *
   * @param array<int|string, string> $tables
   *   A list of table names (strings).
   *
   * @return int
   *   The sum of sizes in bytes for all provided tables.
   */
  public function getTotalSizeBytes(array $tables): int;

}
