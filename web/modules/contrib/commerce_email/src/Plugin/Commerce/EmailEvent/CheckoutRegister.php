<?php

namespace Drupal\commerce_email\Plugin\Commerce\EmailEvent;

use Drupal\commerce_checkout\Event\CheckoutRegisterEvent;
use Drupal\Component\EventDispatcher\Event;

/**
 * Provides the CheckoutRegister email event.
 *
 * @CommerceEmailEvent(
 *   id = "checkout_register",
 *   label = @Translation("Checkout register"),
 *   event_name = "commerce_checkout.checkout_register",
 *   entity_type = "user",
 * )
 */
class CheckoutRegister extends EmailEventBase {

  /**
   * {@inheritdoc}
   */
  public function extractEntityFromEvent(Event $event) {
    assert($event instanceof CheckoutRegisterEvent);
    return $event->getAccount();
  }

  /**
   * {@inheritdoc}
   */
  public function getRelatedEntityTypeIds() {
    return [
      'commerce_order',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function extractRelatedEntitiesFromEvent(Event $event) {
    assert($event instanceof CheckoutRegisterEvent);
    return [
      $event->getOrder(),
    ];
  }

}
