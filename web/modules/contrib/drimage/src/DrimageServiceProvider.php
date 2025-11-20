<?php

namespace Drupal\drimage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\drimage\EventSubscriber\DrimageStageFileProxySubscriber;
use Drupal\stage_file_proxy\EventSubscriber\StageFileProxySubscriber;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines a service modifier for the Drimage module.
 */
final class DrimageServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $arguments = [
      new Reference('drimage.stage_file_proxy_subscriber.inner'),
      new Reference('path_processor_manager'),
      new Reference('http_kernel'),
      new Reference('image.factory'),
      new Reference('file_url_generator'),
    ];

    if ($container->hasDefinition('stage_file_proxy.proxy_subscriber')) {
      $container->register('drimage.stage_file_proxy_subscriber', DrimageStageFileProxySubscriber::class)
        ->setDecoratedService('stage_file_proxy.proxy_subscriber')
        ->setArguments($arguments)
        ->setPublic(FALSE);
    }

    if ($container->hasDefinition(StageFileProxySubscriber::class)) {
      $container->register('drimage.stage_file_proxy_subscriber', DrimageStageFileProxySubscriber::class)
        ->setDecoratedService(StageFileProxySubscriber::class)
        ->setArguments($arguments)
        ->setPublic(FALSE);
    }
  }

}
