<?php

namespace Drupal\media_bulk_upload;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media_bulk_upload\Entity\MediaBulkConfig;

/**
 * Provides dynamic permissions for nodes of different types.
 */
class MediaBulkConfigPermissions {
  use StringTranslationTrait;

  /**
   * Constructs a new MediaBulkConfigPermissions.
   */
  public function __construct() {
  }

  /**
   * The config permissions.
   *
   * @return array
   *   The config permissions.
   */
  public function mediaBulkConfigPermissions() {
    $permissions = [];
    foreach (MediaBulkConfig::loadMultiple() as $mediaBulkConfig) {
      $permissions += $this->buildPermissions($mediaBulkConfig);
    }

    return $permissions;
  }

  /**
   * Returns a list of node permissions for a given node type.
   *
   * @param Drupal\media_bulk_upload\Entity\MediaBulkConfig $mediaBulkConfig
   *   Configuration entity.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(MediaBulkConfig $mediaBulkConfig) {
    $mediaBulkConfigId = $mediaBulkConfig->id();
    $mediaBulkConfigLabel = ['%type_name' => $mediaBulkConfig->label()];

    return [
      "use $mediaBulkConfigId bulk upload form" => [
        'title' => $this->t('%type_name: Use upload form', $mediaBulkConfigLabel),
      ],
    ];
  }

}
