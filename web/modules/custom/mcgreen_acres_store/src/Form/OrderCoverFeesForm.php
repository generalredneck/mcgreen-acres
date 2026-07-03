<?php

namespace Drupal\mcgreen_acres_store\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A tiny standalone form for toggling field_cover_stripe_fees in place.
 *
 * Embedded via CoverFeesFormFormatter so admins can toggle the "Support the
 * Farm" fee directly from an order's canonical page (Commerce 3.3+ routes
 * "Add order" straight to that view, with items added inline from there) —
 * without needing to visit the separate Edit tab just for this one field.
 */
class OrderCoverFeesForm extends FormBase {

  /**
   * The order being edited.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * Sets the order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function setEntity(OrderInterface $order): void {
    $this->order = $order;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mcgreen_acres_store_order_cover_fees_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#cache']['max-age'] = 0;

    $cover_fees = $this->order->get('field_cover_stripe_fees')->value;
    // NULL (never set) defaults to opted-in, matching CreditCardProcessingFee.
    $checked = ($cover_fees === NULL || (bool) $cover_fees);

    $form['cover_fees'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Support the Farm fee applied'),
      '#default_value' => $checked,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->order->set('field_cover_stripe_fees', (bool) $form_state->getValue('cover_fees'));
    $this->order->save();
    $this->messenger()->addStatus($this->t('Updated the "Support the Farm" fee setting for this order.'));
  }

}
