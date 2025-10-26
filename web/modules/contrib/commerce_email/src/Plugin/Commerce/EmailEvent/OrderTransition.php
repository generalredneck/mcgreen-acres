<?php

namespace Drupal\commerce_email\Plugin\Commerce\EmailEvent;

use Drupal\Component\EventDispatcher\Event;
use Drupal\state_machine\Event\WorkflowTransitionEvent;

/**
 * Provides the OrderTransition email event.
 *
 * @CommerceEmailEvent(
 *   id = "order_transition",
 *   label = @Translation("Order transition"),
 *   entity_type = "commerce_order",
 *   deriver = "Drupal\commerce_email\Plugin\Commerce\EmailEvent\OrderTransitionDeriver"
 * )
 */
class OrderTransition extends EmailEventBase {

  /**
   * {@inheritdoc}
   */
  public function extractEntityFromEvent(Event $event) {
    assert($event instanceof WorkflowTransitionEvent);
    return $event->getEntity();
  }

}
