<?php

namespace Drupal\webform_ip_geo\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Webform IP Geo Plugin item annotation object.
 *
 * @see \Drupal\webform_ip_geo\Plugin\WebformIpGeoPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class WebformIpGeoPlugin extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
