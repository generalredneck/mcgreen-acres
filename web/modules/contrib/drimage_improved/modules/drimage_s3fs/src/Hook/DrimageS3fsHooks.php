<?php

declare(strict_types=1);

namespace Drupal\drimage_s3fs\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Hook implementations for Drimage S3fs.
 */
class DrimageS3fsHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.drimage_s3fs':
        return '<p>' . t('A module that integrates S3FS for storing images on Amazon S3.') . '</p>';
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'drimage_s3_formatter' => [
        'variables' => [
          'item' => NULL,
          'item_attributes' => NULL,
          'image_style' => NULL,
          'core_webp' => NULL,
          'imageapi_optimize_webp' => NULL,
          'url' => NULL,
          'alt' => NULL,
          'title' => NULL,
          'width' => NULL,
          'height' => NULL,
          'data' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_help().
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(&$libraries, $extension) {
    if ($extension === 'ckeditor5') {
      $libraries['internal.drupal.ckeditor5.media']['dependencies'][] = 'drimage_s3fs/drimage_s3fs';
    }
  }

}
