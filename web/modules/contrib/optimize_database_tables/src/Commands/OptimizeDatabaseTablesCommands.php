<?php

declare(strict_types=1);

namespace Drupal\optimize_database_tables\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\optimize_database_tables\Service\ConnectionRegistry;
use Drupal\optimize_database_tables\Service\OptimizeDatabase;
use Drupal\optimize_database_tables\Utility\TableIdParserTrait;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the optimize_database_tables module.
 *
 * Supports multiple database connections: tables are discovered per
 * connection and results are grouped by connection in the --details table.
 */
class OptimizeDatabaseTablesCommands extends DrushCommands {

  use StringTranslationTrait;
  use TableIdParserTrait;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The connection registry for multi-connection table discovery.
   *
   * @var \Drupal\optimize_database_tables\Service\ConnectionRegistry
   */
  protected ConnectionRegistry $connectionRegistry;

  /**
   * The optimize database service.
   *
   * @var \Drupal\optimize_database_tables\Service\OptimizeDatabase
   */
  protected OptimizeDatabase $optimizeDatabase;

  /**
   * Constructs the Drush command with injected services.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\optimize_database_tables\Service\ConnectionRegistry $connectionRegistry
   *   The connection registry providing handlers per connection key.
   * @param \Drupal\optimize_database_tables\Service\OptimizeDatabase $optimizeDatabase
   *   The optimize database service for building grouped tables.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    ConnectionRegistry $connectionRegistry,
    OptimizeDatabase $optimizeDatabase,
  ) {
    parent::__construct();
    $this->configFactory = $configFactory;
    $this->connectionRegistry = $connectionRegistry;
    $this->optimizeDatabase = $optimizeDatabase;
  }

  /**
   * Run database optimization with progress output.
   *
   * Optimizes either all tables (across configured connections) or the
   * configured subset, according to optimize_database_tables.settings.
   * Displays a SymfonyStyle progress bar via Drush IO. Shows total DB size
   * before/after and saved space; with --details prints a per-table breakdown
   * grouped by connection.
   *
   * @phpstan-param array{details: bool} $options
   */
  #[CLI\Command(name: 'optimize_database_tables:run', aliases: ['optimize-dbt:run'])]
  #[CLI\Option(name: 'details', description: 'Show per-table size details before/after, grouped by connection.')]
  #[CLI\Usage(name: 'drush optimize-dbt:run', description: 'Optimize database tables with a progress bar.')]
  #[CLI\Usage(name: 'drush optimize-dbt:run --details', description: 'Optimize and show per-table size details grouped by connection.')]
  public function run(array $options = ['details' => FALSE]): void {
    $io = $this->io();

    $io->title('Optimize database tables');

    $config = $this->configFactory->get('optimize_database_tables.settings');
    $allTables = (bool) $config->get('all_tables');

    // Build $grouped: [connectionKey => [tableId => tableName]].
    $grouped = $this->optimizeDatabase->buildGroupedTables();

    $totalCount = (int) array_sum(array_map('count', $grouped));

    if ($totalCount === 0) {
      $link = Url::fromRoute('optimize_database_tables.settings')->toString();
      $io->warning((string) $this->t(
        'No tables to optimize. Check configuration at @link.',
        ['@link' => $link]
      ));
      return;
    }

    $connectionCount = count(array_filter($grouped));
    $io->text($allTables
      ? (string) $this->t(
        'Optimizing all tables (@count found across @conn connection(s)).',
        ['@count' => $totalCount, '@conn' => $connectionCount]
      )
      : (string) $this->t(
        'Optimizing @count selected table(s) across @conn connection(s).',
        ['@count' => $totalCount, '@conn' => $connectionCount]
      )
    );

    $showDetails = !empty($options['details']);

    // Measure before sizes per connection.
    $beforeSizes = [];
    $perTableBefore = [];
    foreach ($grouped as $key => $tables) {
      $handler = $this->connectionRegistry->getHandlerForKey($key);
      $beforeSizes[$key] = $handler->getTotalSizeBytes(array_values($tables));
      if ($showDetails) {
        foreach ($tables as $tableId => $tableName) {
          $perTableBefore[$tableId] = $handler->getTableSizeBytes($tableName);
        }
      }
    }

    $io->newLine();
    $io->progressStart($totalCount);
    $start = microtime(TRUE);

    foreach ($grouped as $key => $tables) {
      $handler = $this->connectionRegistry->getHandlerForKey($key);
      foreach ($tables as $tableName) {
        $handler->optimizeTable($tableName);
        $io->progressAdvance();
      }
    }

    $io->progressFinish();

    // Measure after sizes per connection.
    $afterSizes = [];
    foreach ($grouped as $key => $tables) {
      $handler = $this->connectionRegistry->getHandlerForKey($key);
      $afterSizes[$key] = $handler->getTotalSizeBytes(array_values($tables));
    }

    $duration = microtime(TRUE) - $start;

    $io->success((string) $this->t(
      'Optimization finished for @count table(s) in @seconds seconds.',
      ['@count' => $totalCount, '@seconds' => number_format($duration, 2)]
    ));

    // Build a stats table: one row per connection + a TOTAL row.
    $statsRows = [];
    $globalBefore = 0;
    $globalAfter = 0;
    foreach ($grouped as $key => $tables) {
      $before = $beforeSizes[$key] ?? 0;
      $after = $afterSizes[$key] ?? 0;
      $connSaved = max(0, $before - $after);
      $connPercent = $before > 0 ? ($connSaved / $before * 100) : 0.0;
      $globalBefore += $before;
      $globalAfter += $after;
      $statsRows[] = [
        $key,
        $this->connectionRegistry->getDriverForKey($key),
        $this->formatBytes($before),
        $this->formatBytes($after),
        $this->formatBytes($connSaved) . ' (' . number_format($connPercent, 2) . '%)',
      ];
    }

    $globalSaved = max(0, $globalBefore - $globalAfter);
    $globalPercent = $globalBefore > 0 ? ($globalSaved / $globalBefore * 100) : 0.0;
    $statsRows[] = [
      '—',
      (string) $this->t('TOTAL'),
      $this->formatBytes($globalBefore),
      $this->formatBytes($globalAfter),
      $this->formatBytes($globalSaved) . ' (' . number_format($globalPercent, 2) . '%)',
    ];

    $io->table([
      (string) $this->t('Connection'),
      (string) $this->t('Driver'),
      (string) $this->t('Before'),
      (string) $this->t('After'),
      (string) $this->t('Saved'),
    ], $statsRows);

    if ($showDetails) {
      $rows = [];
      foreach ($grouped as $key => $tables) {
        $handler = $this->connectionRegistry->getHandlerForKey($key);
        foreach ($tables as $tableId => $tableName) {
          $before = $perTableBefore[$tableId] ?? $handler->getTableSizeBytes($tableName);
          $after = $handler->getTableSizeBytes($tableName);
          $gain = max(0, $before - $after);
          $rows[] = [
            $key,
            $tableName,
            $this->formatBytes($before),
            $this->formatBytes($after),
            $this->formatBytes($gain),
            ($before > 0) ? (number_format($gain / $before * 100, 2) . '%') : '—',
          ];
        }
      }
      $io->newLine();
      $io->table([
        (string) $this->t('Connection'),
        (string) $this->t('Table'),
        (string) $this->t('Before'),
        (string) $this->t('After'),
        (string) $this->t('Saved'),
        (string) $this->t('% Saved'),
      ], $rows);
    }
  }

}
