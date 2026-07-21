<?php

namespace Drupal\commerce_recurring_log\EventSubscriber;

use Drupal\commerce_recurring\Event\PaymentDeclinedEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_recurring\Event\RecurringEvents;

class DunningSubscriber implements EventSubscriberInterface {

  /**
   * The log storage.
   *
   * @var \Drupal\commerce_log\LogStorageInterface
   */
  protected $logStorage;

  /**
   * Constructs a new DunningSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->logStorage = $entity_type_manager->getStorage('commerce_log');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      RecurringEvents::PAYMENT_DECLINED => [
        ['onPaymentDeclined', 100],
      ],
    ];
  }

  /**
   * Creates a log when a subscription payment was declined.
   *
   * @param \Drupal\commerce_recurring\Event\PaymentDeclinedEvent $event
   *   The payment declined event.
   */
  public function onPaymentDeclined(PaymentDeclinedEvent $event) {
    $order = $event->getOrder();
    try {
      $this->logStorage->generate($order, 'subscription_payment_declined', [
        'retry_days' => $event->getRetryDays(),
        'num_retries' => $event->getNumRetries(),
        'max_retries' => $event->getMaxRetries(),
        'exception' => $event->getException() ? $event->getException()->getMessage() : NULL,
      ])->save();
    } catch (\Drupal\Core\Entity\EntityStorageException $e) {
      \Drupal::logger('commerce_recurring_log')->error('Failed to log declined payment: @message', ['@message' => $e->getMessage()]);
    }
  }

}
