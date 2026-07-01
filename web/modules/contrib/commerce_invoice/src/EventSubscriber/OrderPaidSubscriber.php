<?php

namespace Drupal\commerce_invoice\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPaidSubscriber implements EventSubscriberInterface {

  /**
   * The invoice storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $invoiceStorage;

  /**
   * Constructs a new OrderPaidSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->invoiceStorage = $entity_type_manager->getStorage('commerce_invoice');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [
      'commerce_order.order.paid' => 'onPaid',
    ];
    return $events;
  }

  /**
   * Updates the invoice total paid when an order is paid.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onPaid(OrderEvent $event) {
    $order = $event->getOrder();
    $invoice_ids = $this->invoiceStorage->getQuery()
      ->condition('state', 'pending')
      ->condition('orders', [$order->id()], 'IN')
      ->accessCheck(FALSE)
      ->execute();
    // No pending invoice references the order being paid, aborting.
    if (!$invoice_ids) {
      return;
    }
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface[] $invoices */
    $invoices = $this->invoiceStorage->loadMultiple($invoice_ids);
    foreach ($invoices as $invoice) {
      if ($invoice->isPaid()) {
        continue;
      }
      $total_paid = $invoice->getTotalPaid();
      $total_paid = $total_paid ? $total_paid->add($order->getTotalPaid()) : $order->getTotalPaid();
      $invoice->setTotalPaid($total_paid);
      // Set the order being presave in the offset of the field
      // orders which matches so it can be used for other processes that access
      // this field.
      $orders_field = $invoice->get('orders');
      $orders_id = array_column($orders_field->getValue(), 'target_id');
      $index = array_search($order->id(), $orders_id);
      $orders_field->offsetSet($index, $order);
      $invoice->save();
    }
  }

}
