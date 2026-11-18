<?php

declare(strict_types=1);

namespace Drupal\optimize_database_tables\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\optimize_database_tables\Exception\UnsupportedDriverException;

/**
 * Factory service that creates the correct DbHandlerInterface implementation.
 *
 * Registered in services.yml as a Drupal service factory so that
 * optimize_database_tables.handler is resolved at container-build time
 * using the active database connection driver.
 */
class DbHandlerFactory {

  /**
   * The Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private LoggerChannelFactoryInterface $logger;

  /**
   * Constructs a new DbHandlerFactory.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection used to detect the active driver.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory passed to the concrete handler.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger,
  ) {
    $this->database = $database;
    $this->logger = $logger;
  }

  /**
   * Creates and returns the appropriate DbHandlerInterface implementation.
   *
   * Delegates to createForConnection() using the injected default connection.
   *
   * @return \Drupal\optimize_database_tables\Service\DbHandlerInterface
   *   A driver-specific implementation of DbHandlerInterface.
   *
   * @throws \Drupal\optimize_database_tables\Exception\UnsupportedDriverException
   *   If the active database driver is not mysql or pgsql.
   */
  public function create(): DbHandlerInterface {
    return $this->createForConnection($this->database);
  }

  /**
   * Creates a DbHandlerInterface implementation for an arbitrary Connection.
   *
   * Used by ConnectionRegistry to build handlers for non-default connections.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to build a handler for.
   *
   * @return \Drupal\optimize_database_tables\Service\DbHandlerInterface
   *   A driver-specific implementation of DbHandlerInterface.
   *
   * @throws \Drupal\optimize_database_tables\Exception\UnsupportedDriverException
   *   If the connection driver is not mysql or pgsql.
   */
  public function createForConnection(Connection $connection): DbHandlerInterface {
    $driver = $connection->databaseType();
    return match ($driver) {
      'mysql' => new MysqlDbHandler($connection, $this->logger),
      'pgsql' => new PgsqlDbHandler($connection, $this->logger),
      default => throw new UnsupportedDriverException($driver),
    };
  }

}
