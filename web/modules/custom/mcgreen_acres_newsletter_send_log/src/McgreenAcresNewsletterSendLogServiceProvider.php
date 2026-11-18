<?php

namespace Drupal\mcgreen_acres_newsletter_send_log;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Swaps the Simplenews mailer for the retrying, logging subclass.
 */
class McgreenAcresNewsletterSendLogServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if (!$container->hasDefinition('simplenews.mailer')) {
      return;
    }
    $container->getDefinition('simplenews.mailer')
      ->setClass(RetryingMailer::class)
      ->addMethodCall('setSendLog', [
        new Reference('mcgreen_acres_newsletter_send_log.storage'),
        new Reference('mcgreen_acres_newsletter_send_log.error_collector'),
        new Reference('mcgreen_acres_newsletter_send_log.classifier'),
        new Reference('config.factory'),
      ]);
  }

}
