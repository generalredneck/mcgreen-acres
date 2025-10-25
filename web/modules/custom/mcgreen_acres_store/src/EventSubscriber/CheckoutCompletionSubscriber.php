<?php

namespace Drupal\mcgreen_acres_store\EventSubscriber;

use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Reacts to the checkout completion event.
 */
class CheckoutCompletionSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new GuestCheckoutCompletionSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Set a higher priority than the Commerce subscriber (which defaults to 0)
    // to ensure your code runs *after* the user is created.
    return [
      CheckoutEvents::COMPLETION => ['onCompletion', -10],
    ];
  }

  /**
   * Reacts to the checkout completion event.
   *
   * @param \Drupal\commerce_checkout\Event\CheckoutCompletionEvent $event
   *   The event.
   */
  public function onCompletion(OrderEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getOrder();
    $customer = $order->getCustomer();
    if ($order->get('checkout_flow')->isEmpty() || empty($order->getEmail())) {
      return;
    }

    // Check if the order was placed by a newly created guest user.
    // The account ID will be 0 if the user is anonymous.
    if ($customer) {
      // The user object exists and has been saved by Commerce.
      // Get the billing profile entity from the order.
      $billingProfile = $order->getBillingProfile();
      if ($billingProfile) {
        // Access the address field from the billing profile.
        $address = $billingProfile->get('address')->first();
        if ($address && $customer->hasField('field_name')
          && $customer->hasField('field_last_name')
          && $customer->field_last_name->isEmpty()
          && $customer->field_name->isEmpty()) {

          $firstName = $address->given_name;
          $lastName = $address->family_name;
          $customer->set('field_name', $firstName);
          $customer->set('field_last_name', $lastName);
          $customer->save();
        }
      }
    }
  }
}
