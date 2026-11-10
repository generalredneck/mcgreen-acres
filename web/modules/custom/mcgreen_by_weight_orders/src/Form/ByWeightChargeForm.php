<?php

namespace Drupal\mcgreen_by_weight_orders\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mcgreen_by_weight_orders\ByWeightOrderCharger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Records packed weights and charges the exact amount for a by-weight order.
 */
class ByWeightChargeForm extends FormBase {

  public function __construct(
    protected ByWeightOrderCharger $charger,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mcgreen_by_weight_orders.charger'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mcgreen_by_weight_orders_charge';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?OrderInterface $commerce_order = NULL) {
    $items = $this->charger->getByWeightItems($commerce_order);
    $form_state->set('order_id', $commerce_order->id());
    if (!$items) {
      $form['none'] = ['#markup' => $this->t('This order has no by-weight items.')];
      return $form;
    }
    $payments = $this->charger->getAuthorizedPayments($commerce_order);
    $payment = reset($payments);
    $form['status'] = [
      '#markup' => $payment
        ? $this->t('Authorized: @amount. The customer is charged only the amount for the weights below; the rest of the hold is released.', [
          '@amount' => $payment->getAmount(),
        ])
        : $this->t('No card authorization to capture on this order. Weights will adjust the total; record the payment yourself.'),
    ];
    $form['weights'] = ['#tree' => TRUE];
    foreach ($items as $id => $item) {
      $form['weights'][$id] = [
        '#type' => 'number',
        '#title' => $this->t('@label x @qty: packed weight (lb, total for the line)', [
          '@label' => $item->label(),
          '@qty' => (int) $item->getQuantity(),
        ]),
        '#description' => $this->t('Authorized for this line: @max.', ['@max' => $item->getUnitPrice()->multiply($item->getQuantity())]),
        '#step' => 0.01,
        '#min' => 0.01,
        '#required' => TRUE,
        '#default_value' => $item->get('field_packed_weight')->value,
      ];
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $payment ? $this->t('Charge') : $this->t('Apply weights'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entityTypeManager->getStorage('commerce_order')->load($form_state->get('order_id'));
    try {
      $captured = $this->charger->apply($order, $form_state->getValue('weights'));
      $this->messenger()->addStatus($captured ? $this->t('Charged @amount.', ['@amount' => $captured]) : $this->t('Weights applied. Order total is now @total.', ['@total' => $order->getTotalPrice()]));
      $form_state->setRedirect('entity.commerce_order.canonical', ['commerce_order' => $order->id()]);
    }
    catch (\InvalidArgumentException $e) {
      $this->messenger()->addError($e->getMessage());
      $form_state->setRebuild();
    }
    catch (PaymentGatewayException $e) {
      // The weights and the new total were already saved by this point, so
      // trying again re-applies the same weights and retries the capture.
      $this->logger('mcgreen_by_weight_orders')->error('Capture failed for order @order: @message', [
        '@order' => $order->id(),
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('The weights were saved, but the card could not be charged: @message The hold may have expired or been released. The order stays on the To weigh list until it is charged; try again, or record the payment another way.', ['@message' => $e->getMessage()]));
      $form_state->setRebuild();
    }
  }

}
