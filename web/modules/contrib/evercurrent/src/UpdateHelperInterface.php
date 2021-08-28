<?php

namespace Drupal\evercurrent;

/**
 * The UpdateHelperInterface in case there are more services like it.
 *
 * @package Drupal\evercurrent
 */
interface UpdateHelperInterface {

  /**
   * Send updates to the Maintenance server.
   *
   * @param bool $force_update
   *   A flag that will ignore checks for time or similarity.
   *
   * @return bool
   *   Returns success or failure.
   */
  public function sendUpdates($force_update = TRUE);

}
