<?php

namespace Drupal\mcgreen_subscription_payment\Mail;

use Drupal\commerce\MailHandlerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderTotalSummaryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Sends a "payment due" email for subscriptions billed via manual gateways.
 */
class PaymentDueMail implements PaymentDueMailInterface {

  use StringTranslationTrait;

  protected EntityTypeManagerInterface $entityTypeManager;
  protected MailHandlerInterface $mailHandler;
  protected OrderTotalSummaryInterface $orderTotalSummary;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, MailHandlerInterface $mail_handler, OrderTotalSummaryInterface $order_total_summary) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailHandler = $mail_handler;
    $this->orderTotalSummary = $order_total_summary;
  }

  /**
   * {@inheritdoc}
   */
  public function send(OrderInterface $order): bool {
    $customer = $order->getCustomer();
    if ($customer->isAnonymous()) {
      return FALSE;
    }

    $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $order_type_storage->load($order->bundle());

    $subject = $this->t('Payment due - Order #@number.', [
      '@number' => $order->getOrderNumber(),
    ]);

    $body = [
      '#theme' => 'commerce_recurring_payment_due',
      '#order_entity' => $order,
      '#totals' => $this->orderTotalSummary->buildTotals($order),
    ];
    if ($billing_profile = $order->getBillingProfile()) {
      $profile_view_builder = $this->entityTypeManager->getViewBuilder('profile');
      $body['#billing_information'] = $profile_view_builder->view($billing_profile);
    }

    $params = [
      'id' => 'recurring_payment_due',
      'from' => $order->getStore()->getEmail(),
      'bcc' => $order_type->getReceiptBcc(),
      'order' => $order,
      'langcode' => $customer->getPreferredLangcode(),
    ];

    return $this->mailHandler->sendMail($order->getEmail(), $subject, $body, $params);
  }

}
