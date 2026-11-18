<?php

declare(strict_types=1);

namespace Drupal\optimize_database_tables\Service;

/**
 * Database handler implementation for MySQL / MariaDB.
 *
 * Provides table listing, size calculation, and optimization via
 * MySQL-specific SQL commands: SHOW FULL TABLES, OPTIMIZE TABLE, and
 * INFORMATION_SCHEMA.TABLES size queries.
 */
class MysqlDbHandler extends AbstractDbHandler {

  /**
   * {@inheritdoc}
   */
  public function getTablesList(): array {
    $tables = [];
    try {
      $stmt = $this->database->query(
        "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"
      );
      if ($stmt === NULL) {
        return $tables;
      }
      foreach ($stmt->fetchAll() as $row) {
        $table = (string) current((array) $row);
        if ($table !== '') {
          $tables[$table] = $table;
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->get('optimize_database_tables')->error(
        'Failed to list MySQL tables: @message',
        ['@message' => $e->getMessage()]
      );
    }

    if (!empty($tables)) {
      ksort($tables, SORT_NATURAL | SORT_FLAG_CASE);
    }

    return $tables;
  }

  /**
   * {@inheritdoc}
   */
  public function optimizeTable(string $table_name): void {
    try {
      $quoted = $this->quoteIdentifier($table_name);
      $this->database->query("OPTIMIZE TABLE {$quoted}");
    }
    catch (\Exception $e) {
      $this->logger->get('optimize_database_tables')->error(
        'Failed to optimize MySQL table @table: @message',
        ['@table' => $table_name, '@message' => $e->getMessage()]
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTableSizeBytes(string $table_name): int {
    try {
      $schema = $this->database->getConnectionOptions()['database'] ?? '';
      $stmt = $this->database->query(
        'SELECT COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) AS size
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
        [':schema' => $schema, ':table' => $table_name]
      );
      if ($stmt === NULL) {
        return 0;
      }
      return (int) $stmt->fetchField();
    }
    catch (\Exception $e) {
      $this->logger->get('optimize_database_tables')->error(
        'Failed to get size of MySQL table @table: @message',
        ['@table' => $table_name, '@message' => $e->getMessage()]
      );
      return 0;
    }
  }

  /**
   * Quotes a MySQL table name with backticks to prevent SQL injection.
   *
   * @param string $identifier
   *   The table name to quote.
   *
   * @return string
   *   The safely backtick-quoted identifier.
   */
  protected function quoteIdentifier(string $identifier): string {
    $escaped = str_replace('`', '``', $identifier);
    return '`' . $escaped . '`';
  }

}
