<?php

namespace Drupal\commerce_stripe\EventSubscriber;

use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logs an order activity entry when an order is placed via Express Checkout.
 *
 * StripePaymentElement::onPaymentConfirm() sets the "stripe_express_checkout"
 * order data key when the customer pays via the Express Checkout Element
 * (Apple Pay/Google Pay/etc.), which skips the regular checkout steps
 * entirely. This makes that distinction visible on the order's Activity tab.
 */
class ExpressCheckoutLogSubscriber implements EventSubscriberInterface {

  /**
   * The commerce_log storage.
   *
   * @var \Drupal\commerce_log\LogStorageInterface
   */
  protected $logStorage;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->logStorage = $entity_type_manager->getStorage('commerce_log');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CheckoutEvents::COMPLETION => ['onCompletion', -1000],
    ];
  }

  /**
   * Logs the order activity entry.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onCompletion(OrderEvent $event) {
    $order = $event->getOrder();
    if ($order->getData('stripe_express_checkout', FALSE)) {
      $this->logStorage->generate($order, 'order_placed_express_checkout')->save();
    }
  }

}
