<?php

namespace Drupal\mcgreen_acres_store\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_recurring\Event\RecurringEvents;
use Drupal\commerce_recurring\Event\SubscriptionEvent;

/**
 * Reacts to commerce_subscription entity updates.
 */
class SubscriptionCancellationSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // This subscribes to the 'update' operation on the 'commerce_subscription' entity type.
    $events[RecurringEvents::SUBSCRIPTION_UPDATE][] = ['onSubscriptionCancel', 0];
    return $events;
  }

  /**
   * Called when a commerce_subscription entity is updated.
   *
   * @param \Drupal\commerce_recurring\Event\SubscriptionEvent $event
   *   The entity event object.
   */
  public function onSubscriptionCancel(SubscriptionEvent $event) {

    /** @var \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription */
    $subscription = $event->getSubscription();

    // The key to an update event is checking the original entity values.
    /** @var \Drupal\commerce_recurring\Entity\SubscriptionInterface $original_subscription */
    $original_subscription = $subscription->original;

    if ($subscription->hasScheduledChange('state', 'canceled') && !$original_subscription->hasScheduledChange('state', 'canceled')) {
      $module = 'mcgreen_acres_store';
      // Corresponds to the key in hook_mail().
      $key = 'subscription_cancel_scheduled';
      $to = 'info@mcgreenacres.com';
      $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
      $params = [
        'subject' => 'Subscription Cancellation Scheduled',
        'body' => 'A subscription has been scheduled for cancellation by ' . $subscription->getCustomer()->getDisplayName(),
      ];
      // Set to FALSE to only format the email without sending.
      $send = TRUE;

      \Drupal::service('plugin.manager.mail')->mail($module, $key, $to, $langcode, $params, NULL, $send);
      \Drupal::logger('mcgreen_acres_store')->info('Subscription @id canceled', [
        '@id' => $subscription->id(),
      ]);
    }
  }

}
