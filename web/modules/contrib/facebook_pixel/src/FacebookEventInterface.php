<?php

namespace Drupal\facebook_pixel;

/**
 * Defines the interface for facebook_pixel service.
 */
interface FacebookEventInterface {

  /**
   * Register an event.
   *
   * @param string $event
   *   The event name.
   * @param mixed $data
   *   The event data.
   */
  public function addEvent($event, $data);

  /**
   * Get the facebook events.
   */
  public function getEvents();

}
