<?php

namespace Drupal\mcgreen_acres_store\EventSubscriber;

use Drupal\commerce_recurring\Event\RecurringEvents;
use Drupal\commerce_recurring\Event\SubscriptionEvent;
use Drupal\simplenews\Entity\Subscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps product subscription newsletters in sync.
 *
 * Subscribes/unsubscribes a customer to/from the newsletter mapped to a
 * product variation as their commerce_subscription for it becomes active or
 * canceled.
 */
class ProductSubscriptionNewsletterSubscriber implements EventSubscriberInterface {

  /**
   * Maps product variation ID to the newsletter ID it keeps in sync.
   *
   * @var array
   */
  const VARIATION_NEWSLETTER_MAP = [
    // Herd Share (/product/herd-share).
    28 => 'herd_share_updates',
    // The Weekly Dozen (/product/weekly-dozen).
    45 => 'the_weekly_dozen_updates',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RecurringEvents::SUBSCRIPTION_UPDATE][] = ['onSubscriptionUpdate', 0];
    return $events;
  }

  /**
   * Subscribes or unsubscribes the owner as the subscription state changes.
   *
   * @param \Drupal\commerce_recurring\Event\SubscriptionEvent $event
   *   The subscription event.
   */
  public function onSubscriptionUpdate(SubscriptionEvent $event) {
    $subscription = $event->getSubscription();

    $newsletter_id = self::VARIATION_NEWSLETTER_MAP[$subscription->getPurchasedEntityId()] ?? NULL;
    if (!$newsletter_id) {
      return;
    }

    $original = $subscription->original;
    if (!$original) {
      return;
    }

    $state = $subscription->getState()->getId();
    $original_state = $original->getState()->getId();
    if ($state === $original_state) {
      return;
    }

    $subscriber = Subscriber::loadByUid($subscription->getOwnerId(), TRUE);
    if (!$subscriber) {
      return;
    }

    if ($state === 'active') {
      $subscriber->subscribe($newsletter_id);
      $subscriber->save();
    }
    elseif ($state === 'canceled') {
      $subscriber->unsubscribe($newsletter_id);
      $subscriber->save();
    }
  }

}
