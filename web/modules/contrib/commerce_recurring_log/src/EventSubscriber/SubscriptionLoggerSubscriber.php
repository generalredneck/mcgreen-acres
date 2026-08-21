<?php

namespace Drupal\commerce_recurring_log\EventSubscriber;

use Drupal\commerce_recurring\Event\SubscriptionEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_recurring\Event\RecurringEvents;
use Drupal\commerce_recurring_log\Event\ReactivateWithImmediatePaymentEvent;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\CurrencyFormatter;

class SubscriptionLoggerSubscriber implements EventSubscriberInterface {

  /**
   * The log storage.
   *
   * @var \Drupal\commerce_log\LogStorageInterface
   */
  protected $logStorage;

  /**
   * The currency formatter.
   *
   * @var \Drupal\commerce_price\CurrencyFormatter
   */
  protected $currencyFormatter;

  /**
   * Constructs a new SubscriptionLoggerSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_price\CurrencyFormatter $currency_formatter
   *   The currency formatter.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrencyFormatter $currency_formatter) {
    $this->logStorage = $entity_type_manager->getStorage('commerce_log');
    $this->currencyFormatter = $currency_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      RecurringEvents::SUBSCRIPTION_UPDATE => [
        ['onSubscriptionUpdate', 100],
      ],
      ReactivateWithImmediatePaymentEvent::REACTIVATE_WITH_IMMEDIATE_PAYMENT => [
        ['onReactivateWithImmediatePayment', 100],
      ]
    ];
  }

  /**
   * Creates a log when a subscription is reactivated with immediate payment.
   *
   * @param \Drupal\commerce_recurring_log\Event\ReactivateWithImmediatePaymentEvent $event
   *   The subscription reactivation event.
   */
  public function onReactivateWithImmediatePayment(ReactivateWithImmediatePaymentEvent $event) {
    $subscription = $event->getSubscription();
    $current_user = \Drupal::currentUser();
    $user_name = $current_user->getDisplayName();
    if (!$current_user->isAnonymous()) {
      $user_name = $current_user->getEmail();
    }

    try {
      $this->logStorage->generate($subscription, 'subscription_reactivate_with_immediate_payment', [
        'user_name' => $user_name,
      ])->save();
    } catch (\Drupal\Core\Entity\EntityStorageException $e) {
      \Drupal::logger('commerce_recurring_log')->error('Failed to log subscription amount change: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Creates a log when a subscription is updated.
   *
   * @param \Drupal\commerce_recurring\Event\SubscriptionEvent $event
   *   The subscription event.
   */
  public function onSubscriptionUpdate(SubscriptionEvent $event) {
    $subscription = $event->getSubscription();
    /** @var \Drupal\commerce_recurring\Entity\SubscriptionInterface $original_subscription */
    if (!isset($subscription->original)) {
        return;
    }
    $original_subscription = $subscription->original;

    if ($original_subscription) {
      $old_price = $original_subscription->getUnitPrice();
      $new_price = $subscription->getUnitPrice();

      if ($old_price && $new_price && ($old_price->getNumber() != $new_price->getNumber() || $old_price->getCurrencyCode() != $new_price->getCurrencyCode())) {
        $current_user = \Drupal::currentUser();
        $user_name = $current_user->getDisplayName();
        if (!$current_user->isAnonymous()) {
          $user_name = $current_user->getEmail();
        }

        try {
          $this->logStorage->generate($subscription, 'subscription_amount_changed', [
            'old_amount' => $this->currencyFormatter->format($old_price->getNumber(), $old_price->getCurrencyCode(), ['currency_display' => 'none']),
            'old_currency_code' => $old_price->getCurrencyCode(),
            'new_amount' => $this->currencyFormatter->format($new_price->getNumber(), $new_price->getCurrencyCode(), ['currency_display' => 'none']),
            'new_currency_code' => $new_price->getCurrencyCode(),
            'user_name' => $user_name,
          ])->save();
        } catch (\Drupal\Core\Entity\EntityStorageException $e) {
          \Drupal::logger('commerce_recurring_log')->error('Failed to log subscription amount change: @message', ['@message' => $e->getMessage()]);
        }
      }
    }
  }
}