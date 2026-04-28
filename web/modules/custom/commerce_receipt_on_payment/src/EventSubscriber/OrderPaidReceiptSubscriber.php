<?php

namespace Drupal\commerce_receipt_on_payment\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Mail\OrderReceiptMailInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends a receipt email when an order balance reaches zero (fully paid).
 *
 * Only fires when the order type has send_receipt_on_paid enabled. The
 * commerce_order.order.paid event is guaranteed to fire at most once per order
 * (when the balance first reaches zero).
 */
class OrderPaidReceiptSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\commerce_order\Mail\OrderReceiptMailInterface
   */
  protected $orderReceiptMail;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, OrderReceiptMailInterface $order_receipt_mail) {
    $this->entityTypeManager = $entity_type_manager;
    $this->orderReceiptMail = $order_receipt_mail;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [OrderEvents::ORDER_PAID => ['sendReceiptOnPaid', -100]];
  }

  /**
   * Sends an order receipt email when the order is fully paid.
   */
  public function sendReceiptOnPaid(OrderEvent $event) {
    $order = $event->getOrder();
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $this->entityTypeManager->getStorage('commerce_order_type')->load($order->bundle());

    if (!$order_type->getThirdPartySetting('commerce_receipt_on_payment', 'send_receipt_on_paid', FALSE)) {
      return;
    }

    $this->orderReceiptMail->send($order, $order->getEmail(), $order_type->getReceiptBcc());
  }

}
