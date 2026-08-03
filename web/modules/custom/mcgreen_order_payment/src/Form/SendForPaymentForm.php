<?php

namespace Drupal\mcgreen_order_payment\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mcgreen_order_payment\Mail\PaymentRequestMailInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Assigns the "Pay Order" checkout flow to an order and hands back its URL.
 *
 * Intended for orders staff build by hand (e.g. a custom-priced processed
 * pig order, once the hanging weight is known) that a specific customer
 * needs to pay directly, without the storefront cart/checkout panes that a
 * fresh purchase would show.
 */
class SendForPaymentForm extends ConfirmFormBase {

  /**
   * The order being prepared for payment.
   */
  protected ?OrderInterface $order = NULL;

  /**
   * Constructs a new SendForPaymentForm object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PaymentRequestMailInterface $paymentRequestMail,
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('mcgreen_order_payment.payment_request_mail'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mcgreen_order_payment_send_for_payment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Send order #@number to the customer as an invoice?', [
      '@number' => $this->order->getOrderNumber() ?: $this->order->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $customer = $this->order->getCustomer();
    return $this->t('The customer (%customer) will be emailed a payment link and be able to pay this order directly, with no shipping, tip, or other cart steps shown — just the payment step. The order will not appear in their storefront cart. Payment will be requested within 2 weeks. Do this once the order total is final.', [
      '%customer' => $customer && $customer->isAuthenticated() ? $customer->getDisplayName() : $this->order->getEmail(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->order->toUrl('canonical');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Send Invoice');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?OrderInterface $commerce_order = NULL) {
    $this->order = $commerce_order;

    if ($this->order->getState()->getId() !== 'draft') {
      $form['warning'] = [
        '#markup' => '<p>' . $this->t('This order is no longer in the Draft state, so it cannot be prepared for direct payment.') . '</p>',
      ];
      return $form;
    }

    $customer = $this->order->getCustomer();
    if (!$customer || !$customer->isAuthenticated()) {
      $form['warning'] = [
        '#markup' => '<p>' . $this->t('This order has no customer account assigned yet. Assign it to the customer before preparing it for payment.') . '</p>',
      ];
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->order->getState()->getId() !== 'draft') {
      return;
    }
    $customer = $this->order->getCustomer();
    if (!$customer || !$customer->isAuthenticated()) {
      return;
    }

    $checkout_flow = $this->entityTypeManager
      ->getStorage('commerce_checkout_flow')
      ->load('pay_order');
    $this->order->set('checkout_flow', $checkout_flow);
    $this->order->save();

    $payment_url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $this->order->id(),
    ], ['absolute' => TRUE])->toString();

    // Two weeks from now.
    $deadline_timestamp = $this->time->getRequestTime() + (14 * 86400);
    $mail_sent = $this->paymentRequestMail->send($this->order, $payment_url, $deadline_timestamp);

    if ($mail_sent) {
      $this->messenger()->addStatus($this->t('Order #@number is ready for payment. An email was sent to @email with a link to pay. Link, if you need to resend it another way: <a href=":url">:url</a>', [
        '@number' => $this->order->getOrderNumber() ?: $this->order->id(),
        '@email' => $this->order->getEmail(),
        ':url' => $payment_url,
      ]));
    }
    else {
      $this->messenger()->addWarning($this->t('Order #@number is ready for payment, but the email could not be sent (the customer has no email on file). Send this link to them manually: <a href=":url">:url</a>', [
        '@number' => $this->order->getOrderNumber() ?: $this->order->id(),
        ':url' => $payment_url,
      ]));
    }

    $form_state->setRedirectUrl($this->order->toUrl('canonical'));
  }

}
