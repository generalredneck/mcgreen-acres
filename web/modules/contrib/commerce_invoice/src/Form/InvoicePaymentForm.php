<?php

namespace Drupal\commerce_invoice\Form;

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class InvoicePaymentForm extends ConfirmFormBase {

  /**
   * The invoice.
   *
   * @var \Drupal\commerce_invoice\Entity\InvoiceInterface
   */
  protected $invoice;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_invoice_payment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Are you sure you want to pay the %label?', [
      '%label' => $this->invoice->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Pay');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to pay the %title?', [
      '%label' => $this->invoice->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.commerce_invoice.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?InvoiceInterface $commerce_invoice = NULL) {
    $this->invoice = $commerce_invoice;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->invoice->isPaid() && $this->invoice->getTotalPrice()) {
      $this->invoice->setTotalPaid($this->invoice->getTotalPrice());
      $this->invoice->save();
    }

    if ($this->invoice->isPaid()) {
      $this->messenger()->addMessage($this->t('%label has been successfully paid.', [
        '%label' => $this->invoice->label(),
      ]));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
