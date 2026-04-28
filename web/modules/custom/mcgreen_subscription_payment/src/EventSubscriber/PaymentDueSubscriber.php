<?php

namespace Drupal\mcgreen_subscription_payment\EventSubscriber;

use Drupal\mcgreen_subscription_payment\Event\ManualPaymentSubscriptionEvents;
use Drupal\mcgreen_subscription_payment\Event\PaymentDueEvent;
use Drupal\mcgreen_subscription_payment\Mail\PaymentDueMailInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends a payment due email when a manual-payment recurring order is placed.
 */
class PaymentDueSubscriber implements EventSubscriberInterface {

  protected PaymentDueMailInterface $paymentDueMail;

  public function __construct(PaymentDueMailInterface $payment_due_mail) {
    $this->paymentDueMail = $payment_due_mail;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ManualPaymentSubscriptionEvents::PAYMENT_DUE => ['sendPaymentDueEmail', -100],
    ];
  }

  /**
   * Sends a payment due email.
   */
  public function sendPaymentDueEmail(PaymentDueEvent $event) {
    if (empty($event->getOrder()->getEmail())) {
      return;
    }
    $this->paymentDueMail->send($event->getOrder());
  }

}
