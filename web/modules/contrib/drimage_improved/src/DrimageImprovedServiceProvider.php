<?php

namespace Drupal\drimage_improved;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\drimage_improved\EventSubscriber\DrimageStageFileProxySubscriber;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines a service provider for the Drimage module.
 */
final class DrimageImprovedServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->register('drimage_improved.stage_file_proxy.proxy_subscriber', DrimageStageFileProxySubscriber::class)
      ->setDecoratedService('Drupal\stage_file_proxy\EventSubscriber\StageFileProxySubscriber', 'stage_file_proxy.proxy_subscriber.inner', 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
      ->addArgument(new Reference('stage_file_proxy.proxy_subscriber.inner'))
      ->addArgument(new Reference('path_processor_manager'))
      ->addArgument(new Reference('kernel'))
      ->addArgument(new Reference('image.factory'))
      ->addArgument(new Reference('file_url_generator'));
  }

}
