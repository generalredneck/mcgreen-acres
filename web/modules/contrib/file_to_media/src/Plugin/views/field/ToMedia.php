<?php

namespace Drupal\file_to_media\Plugin\views\field;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\file_to_media\FileToMediaAccessTrait;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a handler that renders a drop button to allow creation of media.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("file_to_media")
 */
class ToMedia extends FieldPluginBase {

  use FileToMediaAccessTrait;

  /**
   * File usage calculation.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileUsage = $container->get('file.usage');
    $instance->doCreate($container);
    return $instance;
  }

  /**
   * Gets file usage.
   *
   * @param \Drupal\file\FileInterface $file
   *   File.
   *
   * @return int
   *   Count of usage.
   */
  private function getFileUsages(FileInterface $file) : int {
    return array_reduce(array_filter($this->fileUsage->listUsage($file), function (array $module_usage) {
      return array_key_exists('media', $module_usage);
    }), function (int $count, array $module_usage) {
      return $count + array_reduce($module_usage, function (int $object_count, array $object_usage) {
          return $object_count + count($object_usage);
      }, 0);
    }, 0);
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $file = $this->getEntity($values);
    $links = [];
    $cache = CacheableMetadata::createFromObject($file);
    $type_storage = $this->entityTypeManager->getStorage('media_type');
    $cache->addCacheTags(['media_type_list', 'media_list']);
    // Hide the creating link for files that
    // are not used or are private.
    if ($this->getFileUsages($file) || !$this->isPublicDownloadable($file)) {
      $build = ['#markup' => ''];
      $cache->applyTo($build);
      return $build;
    }
    $filename = $file->getFilename();
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    foreach ($type_storage->loadMultiple() as $media_type) {
      $access = $this->hasCreateAccessToMediaType($media_type);
      $cache->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      if (!$this->sourceFieldIsCompatible($media_type->getSource()->getSourceFieldDefinition($media_type), $extension)) {
        continue;
      }
      $links[] = [
        'title' => $this->t('Create @type', ['@type' => $media_type->label()]),
        'url' => Url::fromRoute('file_to_media.add_form', [
          'media_type' => $media_type->id(),
          'file' => $file->id(),
        ]),
      ];
    }
    if (!empty($links)) {
      $build = [
        '#type' => 'dropbutton',
        '#links' => $links,
      ];
      $cache->applyTo($build);
      return $build;
    }
    $build = ['#markup' => ''];
    $cache->applyTo($build);
    return $build;
  }

}
