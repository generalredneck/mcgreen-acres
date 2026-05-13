<?php

namespace Drupal\commerce_invoice\EventSubscriber;

use Drupal\commerce_invoice\Entity\InvoiceType;
use Drupal\commerce_invoice\InvoiceFileManagerInterface;
use Drupal\commerce_invoice\Mail\InvoiceConfirmationMailInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends a confirmation email when an invoice is confirmed or paid.
 *
 * Uses pre-transition events to flag invoices and post-transition events
 * to send the email, preventing duplicate sends.
 */
class InvoiceConfirmationSubscriber implements EventSubscriberInterface {

  /**
   * Static cache of invoice IDS for which the confirmation email has been sent.
   *
   * @var array
   */
  protected array $confirmedInvoiceIds = [];

  /**
   * Constructs a new InvoiceConfirmationSubscriber object.
   *
   * @param \Drupal\commerce_invoice\Mail\InvoiceConfirmationMailInterface $invoiceConfirmationMail
   *   The mail handler.
   * @param \Drupal\commerce_invoice\InvoiceFileManagerInterface $invoiceFileManager
   *   The invoice file manager.
   */
  public function __construct(
    protected InvoiceConfirmationMailInterface $invoiceConfirmationMail,
    protected InvoiceFileManagerInterface $invoiceFileManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_invoice.confirm.pre_transition' => ['preSendInvoiceConfirmation', -100],
      'commerce_invoice.pay.pre_transition' => ['preSendInvoiceConfirmation', -100],
      'commerce_invoice.confirm.post_transition' => ['sendInvoiceConfirmation', -100],
      'commerce_invoice.pay.post_transition' => ['sendInvoiceConfirmation', -100],
    ];
  }

  /**
   * Sends an invoice confirmation email.
   *
   * Note we both react to the "pre" transition and the "post" transition to
   * be able to store a flag without having to resave the invoice.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function preSendInvoiceConfirmation(WorkflowTransitionEvent $event): void {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $event->getEntity();
    // The confirmation email was already queued for sending.
    if ($invoice->getData('confirmation_email_queued')) {
      return;
    }
    $invoice_type = InvoiceType::load($invoice->bundle());
    if ($invoice_type?->shouldSendConfirmation()) {
      // Pregenerate the invoice file so the file reference is saved on the
      // invoice early.
      // Otherwise, the file reference will be saved on the invoice when the
      // invoice file manager is invoked by the invoice confirmation mail during
      // a post save event which causes the same transition to be fired twice.
      $this->invoiceFileManager->getInvoiceFile($invoice, TRUE);
      // Store the transition ID which initiated the confirmation email to
      // ensure we only consider this transition from sendInvoiceConfirmation().
      $invoice->setData('confirmation_email_transition', $event->getTransition()->getId());
      // Storing a flag on the invoice will ensure we don't requeue the same
      // confirmation email from two different transitions at different times.
      $invoice->setData('confirmation_email_queued', TRUE);
    }
  }

  /**
   * Sends an invoice confirmation email.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function sendInvoiceConfirmation(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $event->getEntity();
    // The confirmation email was already sent for this invoice, stop here.
    if (array_key_exists($invoice->id(), $this->confirmedInvoiceIds)) {
      return;
    }
    $transition = $event->getTransition();
    if ($invoice->getData('confirmation_email_queued') &&
      $invoice->getData('confirmation_email_transition') === $transition->getId()) {
      $this->confirmedInvoiceIds[$invoice->id()] = TRUE;
      $invoice_type = InvoiceType::load($invoice->bundle());
      $this->invoiceConfirmationMail->send($invoice, $invoice->getEmail(), $invoice_type?->getConfirmationBcc());
    }
  }

}
