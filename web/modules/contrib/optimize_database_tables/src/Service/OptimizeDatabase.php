<?php

declare(strict_types=1);

namespace Drupal\optimize_database_tables\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\optimize_database_tables\Form\OptimizeDatabaseTablesConfigForm;
use Drupal\optimize_database_tables\Utility\TableIdParserTrait;

/**
 * Service that orchestrates database table optimization via Drupal batches.
 *
 * Reads configuration to determine which connections and tables to optimize,
 * builds batch operations grouped by connection key, and reports aggregate
 * results (before/after sizes, bytes saved) to the messenger service.
 *
 * Table identities are encoded as "connectionKey::tableName" strings so that
 * a single flat results array can carry data from multiple connections.
 */
class OptimizeDatabase {

  use StringTranslationTrait;
  use DependencySerializationTrait;
  use TableIdParserTrait;

  /**
   * Constructs the OptimizeDatabase service.
   *
   * @param \Drupal\optimize_database_tables\Service\ConnectionRegistry $connectionRegistry
   *   The connection registry that provides handlers per connection key.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    protected ConnectionRegistry $connectionRegistry,
    protected ConfigFactoryInterface $configFactory,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * Starts a batch process to optimize selected or all database tables.
   *
   * When all_tables is TRUE the configured connection keys are used to
   * discover every table in each connection. When FALSE, the table_list
   * config values (encoded as "key::table" strings) are parsed and grouped
   * by their connection key.
   *
   * @return void
   *   No return value. Initialises a Drupal batch and exits.
   */
  public function optimizeDatabase(): void {
    // Build $grouped: [connectionKey => [tableId => tableName]].
    $grouped = $this->buildGroupedTables();

    // Compute before-sizes per connection (table names, not IDs).
    $beforeSizes = [];
    foreach ($grouped as $key => $tables) {
      $handler = $this->connectionRegistry->getHandlerForKey($key);
      $beforeSizes[$key] = $handler->getTotalSizeBytes(array_values($tables));
    }

    $operations = [];
    $operations[] = [
      [$this, 'initSizeContext'],
      [$beforeSizes],
    ];

    foreach ($grouped as $key => $tables) {
      foreach ($tables as $tableName) {
        $operations[] = [
          [$this, 'optimizeTable'],
          [$key, $tableName],
        ];
      }
    }

    batch_set([
      'title' => (string) $this->t('Optimize Database Tables'),
      'init_message' => (string) $this->t('Optimize Database Tables'),
      'operations' => $operations,
      'finished' => [$this, 'endOptimizeBatch'],
    ]);
  }

  /**
   * Optimizes a single table on the given connection and records the result.
   *
   * Called as a batch operation callback. Stores the table name in
   * $context['results'] under the key "connectionKey::tableName" so that
   * endOptimizeBatch() can reconstruct groupings per connection.
   *
   * @param string $connectionKey
   *   The connection key identifying which database to use.
   * @param string $table
   *   The bare table name to optimize.
   * @param array<string, mixed> $context
   *   The batch context array.
   *
   * @return void
   *   No return value. Updates batch context with progress and results.
   */
  public function optimizeTable(string $connectionKey, string $table, array &$context): void {
    $context['message'] = (string) $this->t(
      'Optimizing @key::@table',
      ['@key' => $connectionKey, '@table' => $table]
    );
    try {
      $this->connectionRegistry->getHandlerForKey($connectionKey)->optimizeTable($table);
      $context['results'][$connectionKey . '::' . $table] = $table;
    }
    catch (\Exception $e) {
      $this->messenger->addError($e->getMessage());
    }
  }

  /**
   * Stores per-connection before-sizes in the batch context.
   *
   * @param array<string, int> $beforeSizes
   *   Map of connectionKey => total size in bytes before optimization.
   * @param array<string, mixed> $context
   *   The batch context array.
   *
   * @return void
   *   No return value.
   */
  public function initSizeContext(array $beforeSizes, array &$context): void {
    $context['results']['_beforeSizes'] = $beforeSizes;
  }

  /**
   * Finalises the optimization batch and reports aggregate size savings.
   *
   * Reconstructs per-connection groupings from the results array, queries
   * after-sizes for each connection, then computes totals across all
   * connections and displays a summary message.
   *
   * @param bool $success
   *   Whether the batch completed without errors.
   * @param array<string, mixed> $results
   *   The accumulated batch results array.
   * @param array<mixed> $operations
   *   Any remaining unprocessed operations.
   *
   * @return void
   *   No return value. Sends summary messages to the messenger service.
   */
  public function endOptimizeBatch(bool $success, array $results, array $operations): void {
    if (!$success) {
      return;
    }

    /** @var array<string, int> $beforeSizes */
    $beforeSizes = is_array($results['_beforeSizes'] ?? NULL) ? $results['_beforeSizes'] : [];

    // Group successfully optimized table names by connection key.
    $tablesByKey = [];
    foreach ($results as $resultKey => $tableName) {
      if ($resultKey === '_beforeSizes') {
        continue;
      }
      [$key] = self::parseTableId($resultKey);
      $tablesByKey[$key][] = is_string($tableName) ? $tableName : (string) $tableName;
    }

    $globalBefore = 0;
    $globalAfter = 0;
    $totalCount = 0;

    // Collect per-connection stats first, then emit all messages together.
    $perConnectionMessages = [];
    foreach ($tablesByKey as $key => $tables) {
      $handler = $this->connectionRegistry->getHandlerForKey($key);
      $before = (int) ($beforeSizes[$key] ?? 0);
      $after = $handler->getTotalSizeBytes($tables);
      $connSaved = max(0, $before - $after);
      $connPercent = $before > 0 ? ($connSaved / $before * 100) : 0.0;

      $globalBefore += $before;
      $globalAfter += $after;
      $totalCount += count($tables);

      $perConnectionMessages[] = $this->t(
        '@key — Before: @before, After: @after, Saved: @saved (@percent%).',
        [
          '@key' => $key,
          '@before' => $this->formatBytes($before),
          '@after' => $this->formatBytes($after),
          '@saved' => $this->formatBytes($connSaved),
          '@percent' => number_format($connPercent, 2),
        ]
      );
    }

    $this->messenger->addMessage($this->t(
      'Optimization finished for @count table(s).',
      ['@count' => $totalCount]
    ));

    foreach ($perConnectionMessages as $line) {
      $this->messenger->addMessage($line);
    }

    if (count($tablesByKey) > 1) {
      $globalSaved = max(0, $globalBefore - $globalAfter);
      $globalPercent = $globalBefore > 0 ? ($globalSaved / $globalBefore * 100) : 0.0;
      $this->messenger->addMessage($this->t(
        'Total — Before: @before, After: @after, Saved: @saved (@percent%).',
        [
          '@before' => $this->formatBytes($globalBefore),
          '@after' => $this->formatBytes($globalAfter),
          '@saved' => $this->formatBytes($globalSaved),
          '@percent' => number_format($globalPercent, 2),
        ]
      ));
    }
  }

  /**
   * Builds the grouped tables map from current configuration.
   *
   * Returns a map of [connectionKey => [tableId => tableName]] based on the
   * module settings: either all tables across configured connections, or the
   * explicit table_list selection.
   *
   * @return array<string, array<string, string>>
   *   Outer key is the connection key; inner array maps tableId => tableName.
   */
  public function buildGroupedTables(): array {
    $config = $this->configFactory->get(
      OptimizeDatabaseTablesConfigForm::OPTIMIZE_DATABASE_SETTINGS
    );

    if ((bool) $config->get('all_tables')) {
      $rawKeys = $config->get('connections');
      $keys = is_array($rawKeys) && !empty($rawKeys) ? $rawKeys : ['default'];
      return $this->connectionRegistry->getAllTablesGrouped($keys);
    }

    $rawList = $config->get('table_list');
    $tableList = is_array($rawList) ? $rawList : [];
    $grouped = [];
    foreach ($tableList as $tableId) {
      [$key, $table] = self::parseTableId((string) $tableId);
      $grouped[$key][(string) $tableId] = $table;
    }
    return $grouped;
  }

}
