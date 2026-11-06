<?php

namespace Drupal\symfony_mailer_queue;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines the static language negotiator when the language module is enabled.
 */
class SymfonyMailerQueueServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {

    if ($container->hasDefinition('plugin.manager.language_negotiation_method')) {

      $container->register('symfony_mailer_queue.static_language_negotiator', StaticLanguageNegotiator::class)
        ->addArgument(new Reference('language_manager'))
        ->addArgument(new Reference('plugin.manager.language_negotiation_method'))
        ->addArgument(new Reference('config.factory'))
        ->addArgument(new Reference('settings'))
        ->addArgument(new Reference('request_stack'))
        ->addMethodCall('initLanguageManager');
    }
  }

}
