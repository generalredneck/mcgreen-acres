<?php

declare(strict_types=1);

namespace Drupal\optimize_database_tables\Service;

use Drupal\Core\Database\Database;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Discovers all configured database connections and creates handlers for them.
 *
 * Reads all connection keys from Database::getAllConnectionInfo() (i.e. every
 * key defined in $databases in settings.php) and delegates handler creation
 * to DbHandlerFactory so the rest of the module stays connection-agnostic.
 */
class ConnectionRegistry {

  /**
   * Runtime cache of instantiated handlers, keyed by connection key.
   *
   * @var array<string, \Drupal\optimize_database_tables\Service\DbHandlerInterface>
   */
  private array $handlerCache = [];

  /**
   * Runtime cache of opened connections, keyed by connection key.
   *
   * Populated alongside $handlerCache so that getDriverForKey() can reuse
   * the same Connection object instead of opening a second one.
   *
   * @var array<string, \Drupal\Core\Database\Connection>
   */
  private array $connectionCache = [];

  /**
   * Constructs a new ConnectionRegistry.
   *
   * @param \Drupal\optimize_database_tables\Service\DbHandlerFactory $handlerFactory
   *   The handler factory used to build driver-specific handlers.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   */
  public function __construct(
    private readonly DbHandlerFactory $handlerFactory,
    private readonly LoggerChannelFactoryInterface $logger,
  ) {}

  /**
   * Returns all connection keys defined in settings.php.
   *
   * @return string[]
   *   Array of connection key strings, e.g. ['default', 'external'].
   */
  public function getAvailableConnectionKeys(): array {
    return array_keys(Database::getAllConnectionInfo());
  }

  /**
   * Returns the database driver type for a given connection key.
   *
   * Reuses the connection opened by getHandlerForKey() to avoid opening a
   * second connection to the same target.
   *
   * @param string $key
   *   The connection key (e.g. 'default', 'external').
   *
   * @return string
   *   The driver name, e.g. 'mysql' or 'pgsql'.
   */
  public function getDriverForKey(string $key): string {
    $this->getHandlerForKey($key);
    return $this->connectionCache[$key]->databaseType();
  }

  /**
   * Returns a DbHandlerInterface instance for the given connection key.
   *
   * Handlers are cached per key for the lifetime of this service instance.
   *
   * @param string $key
   *   The connection key.
   *
   * @return \Drupal\optimize_database_tables\Service\DbHandlerInterface
   *   The driver-specific handler for that connection.
   */
  public function getHandlerForKey(string $key): DbHandlerInterface {
    if (!isset($this->handlerCache[$key])) {
      $connection = Database::getConnection('default', $key);
      $this->connectionCache[$key] = $connection;
      $this->handlerCache[$key] = $this->handlerFactory->createForConnection($connection);
    }
    return $this->handlerCache[$key];
  }

  /**
   * Returns tables for a single connection key as a tableId => tableName map.
   *
   * The table ID is prefixed with the connection key using the '::' separator,
   * e.g. 'default::node' or 'external::public.accounts'. The label value is
   * the bare table name as returned by the underlying handler.
   *
   * @param string $key
   *   The connection key.
   *
   * @return array<string, string>
   *   Map of tableId => tableName, e.g. ['default::node' => 'node', ...].
   */
  public function getTablesForKey(string $key): array {
    $handler = $this->getHandlerForKey($key);
    $result = [];
    foreach (array_keys($handler->getTablesList()) as $tableName) {
      $result[$key . '::' . $tableName] = $tableName;
    }
    return $result;
  }

  /**
   * Returns all tables for multiple connection keys, grouped by connection key.
   *
   * Failed connections are logged and skipped rather than throwing.
   *
   * @param string[] $keys
   *   Connection keys to query.
   *
   * @return array<string, array<string, string>>
   *   Outer key is the connection key; inner array maps tableId => tableName.
   */
  public function getAllTablesGrouped(array $keys): array {
    $grouped = [];
    foreach ($keys as $key) {
      try {
        $grouped[$key] = $this->getTablesForKey($key);
      }
      catch (\Exception $e) {
        $this->logger->get('optimize_database_tables')->error(
          'Failed to list tables for connection @key: @message',
          ['@key' => $key, '@message' => $e->getMessage()]
        );
        $grouped[$key] = [];
      }
    }
    return $grouped;
  }

}
