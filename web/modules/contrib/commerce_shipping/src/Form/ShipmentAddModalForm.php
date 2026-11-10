<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the add shipment modal form.
 */
class ShipmentAddModalForm extends ShipmentForm {

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected ShippingOrderManagerInterface $shippingOrderManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->routeMatch = $container->get('current_route_match');
    $instance->shippingOrderManager = $container->get('commerce_shipping.order_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    parent::prepareEntity();

    // If the shipment isn't owned by the packer, and the order doesn't already
    // reference shipments, attempt to "pack" the order
    // to ensure the created shipment is automatically refreshed / kept in sync
    // as the order gets updated.
    // We could restrict this to draft orders only, but invoking the packers
    // isn't necessarily a bad idea for non-draft orders.
    if (!$this->entity->getData('owned_by_packer')) {
      $order = $this->entity->getOrder() ?? $this->routeMatch->getParameter('commerce_order');
      if (!$this->shippingOrderManager->hasShipments($order)) {
        $order->setData('commerce_shipping_admin_packer_applies', TRUE);
        $shipments = $this->shippingOrderManager->pack($order);

        if (count($shipments) === 1) {
          $this->entity = $shipments[0];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $order = $this->entity->getOrder();
    $actions['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $order->toUrl(),
      '#attributes' => [
        'class' => ['button', 'button--danger', 'dialog-cancel'],
      ],
    ];

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = parent::save($form, $form_state);
    $order = $this->entity->getOrder();
    $form_state->setRedirectUrl($order->toUrl());
    return $return;
  }

}
