<?php

namespace Drupal\commerce_invoice\EventSubscriber;

use Drupal\commerce_invoice\InvoiceGeneratorInterface;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPlacedSubscriber implements EventSubscriberInterface {

  /**
   * The invoice generator.
   *
   * @var \Drupal\commerce_invoice\InvoiceGeneratorInterface
   */
  protected $invoiceGenerator;

  /**
   * Constructs a new OrderPlacedSubscriber object.
   *
   * @param \Drupal\commerce_invoice\InvoiceGeneratorInterface $invoice_generator
   *   The invoice generator.
   */
  public function __construct(InvoiceGeneratorInterface $invoice_generator) {
    $this->invoiceGenerator = $invoice_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [
      'commerce_order.place.post_transition' => ['onPlace'],
    ];
    return $events;
  }

  /**
   * Generates an invoice when an order is placed if configured to do so.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The event we subscribed to.
   */
  public function onPlace(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = OrderType::load($order->bundle());
    $invoice_settings = $order_type->getThirdPartySettings('commerce_invoice');

    // Check if invoices should be generated automatically when an order
    // is placed for this order type.
    if (empty($invoice_settings['invoice_type']) || empty($invoice_settings['order_placed_generation'])) {
      return;
    }
    $values = [
      'uid' => $order->getCustomerId(),
      'type' => $invoice_settings['invoice_type'],
    ];
    $this->invoiceGenerator->generate([$order], $order->getStore(), $order->getBillingProfile(), $values);
  }

}
