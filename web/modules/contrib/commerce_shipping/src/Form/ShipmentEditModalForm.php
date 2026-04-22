<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the shipment edit modal form.
 */
class ShipmentEditModalForm extends ShipmentForm {

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
        'class' => ['button', 'dialog-cancel'],
      ],
    ];

    // Add destination for the "Delete" button.
    $order = $this->entity->getOrder();
    /** @var \Drupal\Core\Url $url */
    $url = &$actions['delete']['#url'];
    $url->setOption('query', ['destination' => $order->toUrl()->toString()]);

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

  /**
   * {@inheritdoc}
   */
  public function setFormDisplay(EntityFormDisplayInterface $form_display, FormStateInterface $form_state) {
    // Only some components should be shown on this form.
    $allowed_components = ['shipping_method', 'shipping_profile', 'tracking_code'];
    foreach (array_keys($form_display->getComponents()) as $component_name) {
      if (!in_array($component_name, $allowed_components)) {
        $form_display->removeComponent($component_name);
      }
    }
    return parent::setFormDisplay($form_display, $form_state);
  }

}
