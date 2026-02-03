<?php

namespace Drupal\commerce_invoice\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InvoiceSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [
      'commerce_invoice.pay.pre_transition' => ['onPayPreTransition'],
      'commerce_invoice.pay.post_transition' => ['onPayPostTransition'],
    ];
    return $events;
  }

  /**
   * Sets the total_paid field when an invoice is paid.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onPayPreTransition(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $event->getEntity();
    if (!$invoice->isPaid()) {
      $invoice->setTotalPaid($invoice->getTotalPrice());
    }
  }

  /**
   * Mark the orders as paid when an invoice is paid.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onPayPostTransition(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $event->getEntity();
    $orders_field = $invoice->get('orders');
    // When an invoice is paid, we need to mark the referenced orders as paid.
    foreach ($orders_field as $item) {
      $order = $item->entity;
      if (!$order instanceof OrderInterface || $order->isPaid()) {
        continue;
      }
      $order->setTotalPaid($order->getTotalPrice());
      $order->save();
    }
  }

}
