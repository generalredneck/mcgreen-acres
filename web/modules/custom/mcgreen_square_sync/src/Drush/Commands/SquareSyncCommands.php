<?php

namespace Drupal\mcgreen_square_sync\Drush\Commands;

use Drupal\mcgreen_square_sync\SaleFetcher;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Drush commands for the Square sync.
 */
final class SquareSyncCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'mcgreen_square_sync.fetcher')]
    private readonly SaleFetcher $fetcher,
  ) {
    parent::__construct();
  }

  /**
   * Fetches Square sales since the configured start date.
   */
  #[CLI\Command(name: 'square-sync:fetch')]
  public function fetch(): void {
    $count = $this->fetcher->fetch();
    $this->logger()->success(dt('Fetched @count sales from Square.', ['@count' => $count]));
  }

}
