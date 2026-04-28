<?php

namespace Drupal\commerce_receipt_on_payment;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Replaces the core order receipt subscriber with our extended version.
 *
 * The extended subscriber suppresses the "placed" receipt when the order type
 * has "send receipt on paid" enabled, deferring to OrderPaidReceiptSubscriber.
 */
class CommerceReceiptOnPaymentServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('commerce_order.order_receipt_subscriber')) {
      $definition = $container->getDefinition('commerce_order.order_receipt_subscriber');
      $definition->setClass('Drupal\commerce_receipt_on_payment\EventSubscriber\OrderReceiptSubscriber');
    }
  }

}
