<?php

namespace Drupal\commerce_shipping\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an event subscriber reacting to various order transitions.
 */
class OrderSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new OrderSubscriber object.
   *
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shippingOrderManager
   *   The shipping order manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected ShippingOrderManagerInterface $shippingOrderManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.cancel.post_transition' => ['onCancel'],
      'commerce_order.place.post_transition' => ['onPlace'],
      // @todo Remove onValidate/onFulfill once there is a Shipments admin UI.
      'commerce_order.validate.post_transition' => ['onValidate'],
      'commerce_order.fulfill.post_transition' => ['onFulfill'],
      'commerce_order.commerce_order.delete' => ['onOrderDelete'],
    ];
  }

  /**
   * Cancels the order's shipments when the order is canceled.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onCancel(WorkflowTransitionEvent $event): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($order->get('shipments')->referencedEntities() as $shipment) {
      if (!$shipment->getState()->isTransitionAllowed('cancel')) {
        continue;
      }
      $shipment->getState()->applyTransitionById('cancel');
      $shipment->save();
    }
  }

  /**
   * Finalizes the order's shipments when the order is placed.
   *
   * Only used if the workflow does not have a validation step.
   * Otherwise the same logic is handled by onValidate().
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onPlace(WorkflowTransitionEvent $event): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    $to_state = $event->getTransition()->getToState();
    if ($to_state->getId() != 'fulfillment' || !$this->shippingOrderManager->hasShipments($order)) {
      return;
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($order->get('shipments')->referencedEntities() as $shipment) {
      if (!$shipment->getState()->isTransitionAllowed('finalize')) {
        continue;
      }
      $shipment->getState()->applyTransitionById('finalize');
      $shipment->save();
    }
  }

  /**
   * Finalizes the order's shipments when the order is validated.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onValidate(WorkflowTransitionEvent $event): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($order->get('shipments')->referencedEntities() as $shipment) {
      if (!$shipment->getState()->isTransitionAllowed('finalize')) {
        continue;
      }
      $shipment->getState()->applyTransitionById('finalize');
      $shipment->save();
    }
  }

  /**
   * Ships the order's shipments when the order is fulfilled.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onFulfill(WorkflowTransitionEvent $event): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($order->get('shipments')->referencedEntities() as $shipment) {
      if (!$shipment->getState()->isTransitionAllowed('ship')) {
        continue;
      }
      $shipment->getState()->applyTransitionById('ship');
      $shipment->save();
    }
  }

  /**
   * Remove all related shipments when order deleted.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $order_event
   *   Order event.
   */
  public function onOrderDelete(OrderEvent $order_event): void {
    $order = $order_event->getOrder();
    if (!$this->shippingOrderManager->hasShipments($order)) {
      return;
    }
    $shipment_storage = $this->entityTypeManager->getStorage('commerce_shipment');
    $shipment_storage->delete($order->get('shipments')->referencedEntities());
  }

}
