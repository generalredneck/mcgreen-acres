<?php

namespace Drupal\webform_ip_geo\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the Webform IP Geo Plugin plugin manager.
 */
class WebformIpGeoPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new WebformIpGeoPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/WebformIpGeoPlugin', $namespaces, $module_handler, 'Drupal\webform_ip_geo\Plugin\WebformIpGeoPluginInterface', 'Drupal\webform_ip_geo\Annotation\WebformIpGeoPlugin');

    $this->alterInfo('webform_ip_geo_plugin_info');
    $this->setCacheBackend($cache_backend, 'webform_ip_geo_plugin_plugins');
  }

}
