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
 *
 * The "order_receipt" sub-type of the "commerce" mailer policy has the
 * symfony_mailer_queue adjuster applied, so this send is queued rather than
 * blocking the request (e.g. a checkout gateway's onReturn()) that triggers
 * it. See the symfony_mailer.mailer_policy.commerce.order_receipt config.
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

    // Guard against the edge case where ORDER_PAID fires on an order that is
    // still in draft state (e.g. admin recording a payment before the order is
    // formally placed). In that situation order_number is not yet set, which
    // would produce a broken subject like "Order # confirmed".
    //
    // The normal checkout paths are safe: on-site gateways place the order
    // before capturing payment, and off-site gateways are handled by
    // commerce_payment's OrderPaidSubscriber (priority 0) which places the
    // order synchronously before our priority -100 listener runs.
    if (empty($order->getOrderNumber())) {
      // Mirror what OrderNumberSubscriber does when no number pattern exists:
      // fall back to the order ID so token replacement produces a valid value.
      $order->setOrderNumber($order->id());
    }

    $this->orderReceiptMail->send($order, $order->getEmail(), $order_type->getReceiptBcc());
  }

}
