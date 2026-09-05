<?php

declare(strict_types=1);

namespace Drupal\optimize_database_tables\Service;

/**
 * Database handler implementation for PostgreSQL.
 *
 * Provides table listing, size calculation, and optimization via
 * PostgreSQL-specific SQL commands: information_schema queries with schema
 * filtering, VACUUM (ANALYZE), and pg_total_relation_size().
 *
 * Table names returned by this handler are schema-qualified
 * (e.g. "public.users") to avoid ambiguity in multi-schema databases.
 * All methods accepting a table name accept both plain names and
 * schema-qualified names.
 */
class PgsqlDbHandler extends AbstractDbHandler {

  /**
   * Separator between schema and table in a qualified identifier.
   */
  private const SCHEMA_SEPARATOR = '.';

  /**
   * {@inheritdoc}
   *
   * Returns schema-qualified names (e.g., "public.users") to prevent
   * ambiguity when multiple schemas contain tables with the same name.
   * System schemas (pg_catalog, information_schema) are excluded.
   */
  public function getTablesList(): array {
    $tables = [];
    try {
      $stmt = $this->database->query(
        "SELECT table_schema, table_name
         FROM information_schema.tables
         WHERE table_type = 'BASE TABLE'
           AND table_schema NOT IN ('pg_catalog', 'information_schema')
         ORDER BY table_schema, table_name"
      );
      if ($stmt === NULL) {
        return $tables;
      }
      foreach ($stmt->fetchAll() as $row) {
        $qualified = $row->table_schema . self::SCHEMA_SEPARATOR . $row->table_name;
        $tables[$qualified] = $qualified;
      }
    }
    catch (\Exception $e) {
      $this->logger->get('optimize_database_tables')->error(
        'Failed to list PostgreSQL tables: @message',
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
   *
   * Runs VACUUM (ANALYZE) on the given table. Accepts both plain names
   * and schema-qualified names (schema.table). VACUUM cannot run inside an
   * open transaction block, so this method bypasses Drupal's transactional
   * database layer by executing the statement via the raw PDO connection.
   */
  public function optimizeTable(string $table_name): void {
    try {
      $quoted = $this->quoteQualifiedIdentifier($table_name);
      /** @var \PDO $pdo */
      $pdo = $this->database->getClientConnection();
      $pdo->exec("VACUUM (ANALYZE) {$quoted}");
    }
    catch (\Exception $e) {
      $this->logger->get('optimize_database_tables')->error(
        'Failed to optimize PostgreSQL table @table: @message',
        ['@table' => $table_name, '@message' => $e->getMessage()]
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * Uses pg_total_relation_size() with to_regclass() for safety. If the
   * table does not exist, to_regclass() returns NULL and COALESCE yields 0.
   * Accepts both plain and schema-qualified names.
   */
  public function getTableSizeBytes(string $table_name): int {
    try {
      $stmt = $this->database->query(
        'SELECT COALESCE(pg_total_relation_size(to_regclass(:tbl)), 0) AS size',
        [':tbl' => $table_name]
      );
      if ($stmt === NULL) {
        return 0;
      }
      return (int) $stmt->fetchField();
    }
    catch (\Exception $e) {
      $this->logger->get('optimize_database_tables')->error(
        'Failed to get size of PostgreSQL table @table: @message',
        ['@table' => $table_name, '@message' => $e->getMessage()]
      );
      return 0;
    }
  }

  /**
   * Quotes a possibly schema-qualified PostgreSQL identifier.
   *
   * Splits on the first dot to handle "schema.table" notation, quoting
   * each part individually with double-quotes. Plain identifiers (no dot)
   * are quoted as-is.
   *
   * @param string $identifier
   *   A plain table name or a schema-qualified name (e.g. "public.users").
   *
   * @return string
   *   The safely quoted identifier, e.g., "public"."users".
   */
  protected function quoteQualifiedIdentifier(string $identifier): string {
    if (!str_contains($identifier, self::SCHEMA_SEPARATOR)) {
      return $this->quotePart($identifier);
    }
    [$schema, $table] = explode(self::SCHEMA_SEPARATOR, $identifier, 2);
    return $this->quotePart($schema) . self::SCHEMA_SEPARATOR . $this->quotePart($table);
  }

  /**
   * Quotes a single PostgreSQL identifier part with double-quotes.
   *
   * Internal double-quotes are escaped by doubling them per the SQL standard.
   *
   * @param string $part
   *   The identifier part (schema name or table name).
   *
   * @return string
   *   The double-quoted identifier part.
   */
  private function quotePart(string $part): string {
    return '"' . str_replace('"', '""', $part) . '"';
  }

}
