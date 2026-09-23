<?php

declare(strict_types=1);

namespace Drupal\optimize_database_tables\Exception;

/**
 * Thrown when the active database driver is not supported by this module.
 *
 * Currently supported drivers: mysql, pgsql.
 */
final class UnsupportedDriverException extends \RuntimeException {

  /**
   * Constructs the exception with a descriptive message.
   *
   * @param string $driver
   *   The unsupported driver name detected from the active connection.
   */
  public function __construct(string $driver) {
    parent::__construct(
      sprintf(
        'The database driver "%s" is not supported by optimize_database_tables. Supported drivers: mysql, pgsql.',
        $driver
      )
    );
  }

}
