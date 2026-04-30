<?php

namespace Drupal\mcgreen_subscription_payment;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Overrides two commerce_recurring services to support manual payment gateways.
 *
 * 1. commerce_recurring.event_subscriber.order_subscriber — extended to allow
 *    subscription creation when a manual gateway is used at checkout (no stored
 *    payment method exists in that flow).
 *
 * 2. commerce_recurring.order_manager — extended to dispatch a PAYMENT_DUE
 *    event instead of throwing HardDeclineException when a subscription has a
 *    manual gateway and no payment method.
 */
class McgreenSubscriptionPaymentServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('commerce_recurring.event_subscriber.order_subscriber')) {
      $container
        ->getDefinition('commerce_recurring.event_subscriber.order_subscriber')
        ->setClass('Drupal\mcgreen_subscription_payment\EventSubscriber\OrderSubscriber');
    }

    if ($container->hasDefinition('commerce_recurring.order_manager')) {
      $definition = $container->getDefinition('commerce_recurring.order_manager');
      $definition->setClass('Drupal\mcgreen_subscription_payment\RecurringOrderManager');
      // Inject event_dispatcher so we can dispatch the PAYMENT_DUE event.
      $definition->addArgument(new Reference('event_dispatcher'));
    }
  }

}
