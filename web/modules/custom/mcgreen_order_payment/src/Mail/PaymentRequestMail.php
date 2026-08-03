<?php

namespace Drupal\mcgreen_order_payment\Mail;

use Drupal\commerce\MailHandlerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderTotalSummaryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Sends the "your invoice is ready for payment" email.
 */
class PaymentRequestMail implements PaymentRequestMailInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MailHandlerInterface $mailHandler,
    protected OrderTotalSummaryInterface $orderTotalSummary,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function send(OrderInterface $order, string $payment_url, int $deadline_timestamp): bool {
    $customer = $order->getCustomer();
    if ($customer->isAnonymous() || !$order->getEmail()) {
      return FALSE;
    }

    $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $order_type_storage->load($order->bundle());

    // Orders prepared for direct payment stay in the "draft" state (so
    // checkout can still place them once paid), which means they never get
    // a generated order number - that only happens on placement. Fall back
    // to the order ID so the email doesn't show a blank "order #".
    $order_number = $order->getOrderNumber() ?: $order->id();

    $subject = $this->t('Your invoice for order #@number is ready for payment', [
      '@number' => $order_number,
    ]);

    $body = [
      '#theme' => 'mcgreen_order_payment_request',
      '#order_entity' => $order,
      '#order_number' => $order_number,
      '#totals' => $this->orderTotalSummary->buildTotals($order),
      '#payment_url' => $payment_url,
      '#customer_email' => $customer->getEmail(),
      '#deadline_date' => $this->dateFormatter->format($deadline_timestamp, 'custom', 'F j, Y'),
    ];
    if ($billing_profile = $order->getBillingProfile()) {
      $profile_view_builder = $this->entityTypeManager->getViewBuilder('profile');
      $body['#billing_information'] = $profile_view_builder->view($billing_profile);
    }

    $params = [
      'id' => 'order_payment_request',
      'from' => $order->getStore()->getEmailFromHeader(),
      'bcc' => $order_type->getReceiptBcc(),
      'order' => $order,
      'langcode' => $customer->getPreferredLangcode(),
    ];

    return $this->mailHandler->sendMail($order->getEmail(), $subject, $body, $params);
  }

}
