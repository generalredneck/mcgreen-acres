<?php

namespace Drupal\commerce_invoice\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for resending invoice confirmations.
 */
class InvoiceConfirmationResendForm extends ContentEntityConfirmFormBase {

  /**
   * The invoice confirmation mail service.
   *
   * @var \Drupal\commerce_invoice\Mail\InvoiceConfirmationMailInterface
   */
  protected $invoiceConfirmationMail;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->invoiceConfirmationMail = $container->get('commerce_invoice.invoice_confirmation_mail');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to resend the confirmation for %label?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Resend');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $this->entity;
    $result = $this->invoiceConfirmationMail->send($invoice);
    // Drupal's MailManager sets an error message itself, if the sending failed.
    if ($result) {
      $this->messenger()->addMessage($this->t('Confirmation resent.'));
    }

    $form_state->setRedirectUrl($this->entity->toUrl());
  }

}
