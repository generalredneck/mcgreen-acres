<?php

namespace Drupal\commerce_timeslots\EventSubscriber;

use Drupal\commerce_timeslots\Services\CommerceTimeSlots;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class OrderEventSubscriber event subscriber.
 */
class OrderEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The commerce time slots service.
   *
   * @var \Drupal\commerce_timeslots\Services\CommerceTimeSlots
   */
  protected CommerceTimeSlots $commerceTimeSlots;

  /**
   * Constructs a new OrderEventSubscriber object.
   *
   * @param \Drupal\commerce_timeslots\Services\CommerceTimeSlots $commerce_timeslots
   *   The commerce time slots service.
   */
  public function __construct(CommerceTimeSlots $commerce_timeslots) {
    $this->commerceTimeSlots = $commerce_timeslots;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'commerce_order.place.post_transition' => ['onPlace'],
      'commerce_order.cancel.post_transition' => ['onCancel'],
    ];
  }

  /**
   * Place a booking for a certain slot capacity.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onPlace(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();

    $order_time_slot = $order->getData('time_slot');
    $time_slot_config = $order_time_slot['time_slot']['wrapper'];
    if (!empty($time_slot_config)) {
      // Create a new time slot booking but check if there is an available
      // time slot.
      $this->commerceTimeSlots->setBooking(
        $order->id(),
        $order->getData('time_slot_id'),
        $time_slot_config['time'],
        $time_slot_config['date']->format('Y-m-d')
      );
    }
  }

  /**
   * Remove the time slot booking place for a certain slot capacity.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onCancel(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    // Unset the time slot booking in case of the order cancelation.
    $this->commerceTimeSlots->unsetBooking($order->id());
  }

}
