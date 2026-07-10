<?php

namespace Drupal\mcgreen_acres_store\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A tiny standalone form for toggling field_needs_fulfillment in place.
 *
 * Embedded via NeedsFulfillmentFormFormatter so staff can override
 * fulfillment directly from an order's canonical page - most usefully right
 * after creating a manually-entered (cash/Zelle/Square) order, before
 * placing it - without needing to visit the separate Edit tab just for
 * this one field.
 */
class OrderNeedsFulfillmentForm extends FormBase {

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
    return 'mcgreen_acres_store_order_needs_fulfillment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#cache']['max-age'] = 0;

    $needs_fulfillment = $this->order->get('field_needs_fulfillment')->value;

    $form['needs_fulfillment'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Needs Fulfillment'),
      '#description' => $this->t('Check this if the order still needs to be picked up or delivered. Manually-entered orders default to already fulfilled; checkout orders are decided automatically from the items purchased unless you override here.'),
      '#default_value' => (bool) $needs_fulfillment,
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
    $this->order->set('field_needs_fulfillment', (bool) $form_state->getValue('needs_fulfillment'));
    $this->order->save();
    $this->messenger()->addStatus($this->t('Updated the fulfillment setting for this order.'));
  }

}
