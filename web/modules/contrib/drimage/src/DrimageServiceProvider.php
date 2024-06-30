<?php

namespace Drupal\drimage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\drimage\EventSubscriber\DrimageStageFileProxySubscriber;

/**
 * Defines a service modifier for the Drimage module.
 */
final class DrimageServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {

    // Remove the stage file proxy subscriber decorator if the decorated service
    // do not exist. This is necessary as long as the decoration_on_invalid
    // property from services.yml files is not supported.
    if (!$container->hasDefinition('stage_file_proxy.proxy_subscriber')) {
      $container->removeDefinition('drimage.stage_file_proxy.proxy_subscriber');
    }
  }

}
