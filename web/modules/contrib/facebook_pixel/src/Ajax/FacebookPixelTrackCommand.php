<?php

namespace Drupal\facebook_pixel\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsInterface;
use Drupal\Core\Asset\AttachedAssets;

/**
 * Logs an event to facebook pixel.
 *
 * This command is implemented in Drupal.AjaxCommands.prototype.facebook_pixel_
 * track.
 */
class FacebookPixelTrackCommand implements CommandInterface, CommandWithAttachedAssetsInterface {

  /**
   * Event name.
   *
   * @var string
   */
  protected $event;

  /**
   * Event data.
   *
   * @var mixed
   */
  protected $data;

  /**
   * Constructs a \Drupal\facebook_pixel\Ajax\FacebookPixelTrackCommand object.
   *
   * @param string $event
   *   The event name.
   * @param mixed $data
   *   Assigned value.
   */
  public function __construct($event, $data) {
    $this->event = $event;
    $this->data = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'facebook_pixel_track',
      'event' => $this->event,
      'data' => $this->data,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachedAssets() {
    $assets = new AttachedAssets();
    $assets->setLibraries(['facebook_pixel/facebook_pixel_command']);
    return $assets;
  }

}
