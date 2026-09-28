<?php

declare(strict_types=1);

namespace Drupal\optimize_database_tables\Utility;

/**
 * Shared utilities for table ID parsing and byte formatting.
 *
 * Table IDs are encoded as "connectionKey::tableName" strings throughout
 * the module so that a single flat results array can carry data from
 * multiple connections.
 */
trait TableIdParserTrait {

  /**
   * Parses a "connectionKey::tableName" table ID into its two components.
   *
   * @param string $id
   *   The table ID string, e.g. 'default::node'.
   *
   * @return array{0: string, 1: string}
   *   A two-element array: [connectionKey, tableName].
   */
  protected static function parseTableId(string $id): array {
    $parts = explode('::', $id, 2);
    return [$parts[0], $parts[1] ?? ''];
  }

  /**
   * Formats a byte count as a human-readable string (e.g. "1.23 MB").
   *
   * @param int $bytes
   *   The size in bytes.
   * @param int $precision
   *   Number of decimal places. Defaults to 2.
   *
   * @return string
   *   The formatted size string.
   */
  protected function formatBytes(int $bytes, int $precision = 2): string {
    if ($bytes <= 0) {
      return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
    return number_format($bytes / (1024 ** $power), $precision) . ' ' . $units[$power];
  }

}
