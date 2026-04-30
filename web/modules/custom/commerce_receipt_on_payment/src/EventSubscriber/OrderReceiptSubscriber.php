<?php

namespace Drupal\commerce_receipt_on_payment\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order\Mail\OrderReceiptMailInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends a receipt email when an order is placed, unless "send on paid" is set.
 *
 * Replaces commerce_order.order_receipt_subscriber via ServiceProvider. When
 * the order type has send_receipt_on_paid enabled, this subscriber skips
 * sending so that OrderPaidReceiptSubscriber can send it on the paid event.
 */
class OrderReceiptSubscriber implements EventSubscriberInterface {

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
    return ['commerce_order.place.post_transition' => ['sendOrderReceipt', -100]];
  }

  /**
   * Sends an order receipt email on placement, unless "send on paid" takes over.
   */
  public function sendOrderReceipt(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $this->entityTypeManager->getStorage('commerce_order_type')->load($order->bundle());

    // Defer to the paid-event subscriber when the order type opts in.
    if ($order_type->getThirdPartySetting('commerce_receipt_on_payment', 'send_receipt_on_paid', FALSE)) {
      return;
    }

    if ($order_type->shouldSendReceipt()) {
      $this->orderReceiptMail->send($order, $order->getEmail(), $order_type->getReceiptBcc());
    }
  }

}
