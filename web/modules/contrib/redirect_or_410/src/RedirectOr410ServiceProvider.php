<?php

namespace Drupal\redirect_or_410;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\redirect_or_410\EventSubscriber\RedirectRequestSubscriber;
use Symfony\Component\DependencyInjection\Reference;

class RedirectOr410ServiceProvider implements ServiceProviderInterface, ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if (!$container->hasDefinition('redirect.request_subscriber')) {
      return;
    }

    $requestSubscriber = $container->getDefinition('redirect.request_subscriber');
    $arguments = $requestSubscriber->getArguments();

    $arguments[] = new Reference('http_kernel');
    $arguments[] = new Reference('router.no_access_checks');
    $arguments[] = new Reference('redirect.destination');
    $arguments[] = new Reference('access_manager');
    $requestSubscriber
      ->setClass(RedirectRequestSubscriber::class)
      ->setArguments($arguments);
  }

}