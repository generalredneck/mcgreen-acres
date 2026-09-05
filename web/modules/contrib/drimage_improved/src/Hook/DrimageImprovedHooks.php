<?php

declare(strict_types=1);

namespace Drupal\drimage_improved\Hook;

use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\crop\CropTypeInterface;
use Drupal\drimage_improved\Controller\ImageStyleListBuilder;
use Drupal\drimage_improved\Controller\ImageStyleWithPipelineListBuilder;
use Drupal\imageapi_optimize\ImageAPIOptimizePipelineInterface;

/**
 * Hook implementations for Drimage.
 */
class DrimageImprovedHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'drimage_improved.image':
        return '<p>' . t('For a full description of the module, visit the project page: https://drupal.org/project/drimage_improved') . '</p>';
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'drimage_formatter' => [
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
          'placeholder_color' => NULL,
          'placeholder_image' => NULL,
          'placeholder_image_switch' => NULL,
          'fetchpriority' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_help().
   */
  #[Hook('entity_type_alter', order: Order::Last)]
  public function entityTypeAlter(array &$entity_types) : void {
    if (isset($entity_types['imageapi_optimize_pipeline'])) {
      $entity_types['image_style']->setListBuilderClass(ImageStyleWithPipelineListBuilder::class);
    }
    else {
      $entity_types['image_style']->setListBuilderClass(ImageStyleListBuilder::class);
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('modules_uninstalled')]
  public function modulesUninstalled(array $modules, bool $isSyncing) : void {
    if ($isSyncing) {
      // Let's deal with this in drimage_improved_config_import_steps_alter().
      return;
    }

    // When drimage_improved is (un)installed in the same batch as one of these
    // modules the container may not carry its services yet, so guard the call.
    $modulesToCheck = ['image_widget_crop', 'automated_crop', 'focal_point'];
    if (array_intersect($modulesToCheck, $modules) && \Drupal::hasService('drimage_improved.image_style_repository')) {
      \Drupal::service('drimage_improved.image_style_repository')
        ->deleteAll();
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $isSyncing) : void {
    if ($isSyncing) {
      // Let's deal with this in drimage_improved_config_import_steps_alter().
      return;
    }

    // Installing drimage_improved together with one of these modules (for
    // example from a recipe) fires this hook before the container carries the
    // drimage_improved services, so guard the call.
    $modulesToCheck = ['image_widget_crop', 'automated_crop', 'focal_point'];
    if (array_intersect($modulesToCheck, $modules) && \Drupal::hasService('drimage_improved.image_style_repository')) {
      \Drupal::service('drimage_improved.image_style_repository')
        ->deleteAll();
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('config_import_steps_alter')]
  public function configImportStepsAlter(array &$syncSteps, ConfigImporter $configImporter) : void {
    $modulesToCheck = ['image_widget_crop', 'automated_crop', 'focal_point'];
    $uninstalledModules = $configImporter->getExtensionChangelist('module', 'uninstall');
    $installedModules = $configImporter->getExtensionChangelist('module', 'install');

    if (array_intersect($modulesToCheck, $uninstalledModules) || array_intersect($modulesToCheck, $installedModules)) {
      $syncSteps[] = '_drimage_improved_config_import_delete_image_styles';
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('imageapi_optimize_pipeline_update')]
  public function imageapiOptimizePipelineUpdate(ImageAPIOptimizePipelineInterface $entity) : void {
    $config = \Drupal::config('imageapi_optimize.settings');

    if ($config->get('default_pipeline') !== $entity->id()) {
      return;
    }

    \Drupal::service('drimage_improved.image_style_repository')
      ->deleteAll();
  }

  /**
   * Implements hook_help().
   */
  #[Hook('crop_type_update')]
  public function cropTypeUpdate(CropTypeInterface $entity) : void {
    \Drupal::service('drimage_improved.image_style_repository')
      ->deleteByCropType($entity);
  }

  /**
   * Implements hook_help().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments) {
    // Get drimage_improved settings.
    $settings = \Drupal::config('drimage_improved.settings');
    $dimentions = [];
    // Load all image styles.
    $styles = \Drupal::entityTypeManager()
      ->getStorage('image_style')
      ->loadMultiple();
    foreach ($styles as $name => $style) {
      // Calculate the dimensions from the style name.
      $translated_name = str_replace('drimage_improved_', '', $name);
      if (\Drupal::moduleHandler()->moduleExists('focal_point')) {
        $translated_name = str_replace('focal_', '', $translated_name);
      }
      // Skip image styles without drimage_improved_ prefix.
      if ($name == $translated_name) {
        continue;
      }
      $dimensions = explode('_', $translated_name);
      // Skip image styles that will only scale.
      if ($dimensions[1] <= 0) {
        continue;
      }
      $dimentions[] = [
        'name' => $name,
        'width' => $dimensions[0],
        'height' => $dimensions[1],
      ];
    }
    $attachments['#attached']['drupalSettings']['drimage_improved']['ratio_distortion'] = $settings->get('ratio_distortion');
    $attachments['#attached']['drupalSettings']['drimage_improved']['dimentions'] = $dimentions;

    $noscript = [
      '#noscript' => TRUE,
      '#tag' => 'style',
      '#value' => '.drimage-image { display: none; }',
    ];

    $attachments['#attached']['html_head'][] = [$noscript, 'noscript'];
  }

}
