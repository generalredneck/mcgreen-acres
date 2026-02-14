<?php

namespace Drupal\mcgreen_acres_store\EventSubscriber;

use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds a phone number profile to the customer on guest checkout completion.
 */
class GuestCheckoutSetPhoneOnCompletionSubscriber implements EventSubscriberInterface  {

  /**
   * Constructs a new object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected LanguageManagerInterface $languageManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CheckoutEvents::COMPLETION => ['onCompletion', -1000],
    ];
  }

  /**
   * Handles guest checkout completion.
   *
   * Based on the following checkout flow settings:
   * - guest_new_account: creates new guest account.
   * - guest_order_assign: assigns the order to an existing user account.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onCompletion(OrderEvent $event) {
    $order = $event->getOrder();
    $customer = $order->getCustomer();
    if ($customer->get('phone_profiles')->isEmpty()) {
      $phone_number = $order->get('field_order_contact')->isEmpty() ? NULL : $order->get('field_order_contact')->value;
      $profile_type_id = 'phone';
      $profile_type = $this->entityTypeManager->getStorage('profile_type')->load($profile_type_id);
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      /** @var \Drupal\profile\Entity\ProfileInterface $profile */
      $profile = $profile_storage->create([
        'type' => $profile_type_id,
        'uid' => $customer->id(),
        'langcode' => $profile_type->language()
          ? $profile_type->language()
          : $this->languageManager->getDefaultLanguage()->getId(),
      ]);
      $profile->set('field_phone', $phone_number);
      $profile->save();

    }
  }

}
