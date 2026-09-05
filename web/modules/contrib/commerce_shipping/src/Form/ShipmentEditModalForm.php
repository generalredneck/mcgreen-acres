<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\commerce_shipping\Entity\Shipment;
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
    // Ensure required components are always present on the form.
    $required_components = $this->getRequiredComponents();
    foreach ($required_components as $component_name => $component) {
      if (!$form_display->getComponent($component_name)) {
        $form_display->setComponent($component_name, $component);
      }
    }

    // Skip further cleanup if using the configured form display.
    $shipment = $this->getEntity();
    $expected_form_display = sprintf('%s.%s.%s', $shipment->getEntityTypeId(), $shipment->bundle(), $this->getOperation());
    if ($expected_form_display === $form_display->id()) {
      return parent::setFormDisplay($form_display, $form_state);
    }

    // Remove base fields which are not needed on this form.
    $base_fields = Shipment::baseFieldDefinitions($shipment->getEntityType());
    $fields_to_remove = array_diff_key($base_fields, $required_components);
    foreach (array_keys($fields_to_remove) as $field) {
      if ($form_display->getComponent($field)) {
        $form_display->removeComponent($field);
      }
    }

    return parent::setFormDisplay($form_display, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperation(): string {
    // Replace dash with underscore to use form display configuration.
    $operation = parent::getOperation();
    return str_replace('-', '_', $operation);
  }

  /**
   * Returns the list of components that should be presented on this form.
   */
  protected function getRequiredComponents(): array {
    return [
      'shipping_profile' => [
        'type' => 'commerce_shipping_profile',
        'settings' => [],
      ],
      'shipping_method' => [
        'type' => 'commerce_shipping_rate',
        'settings' => [],
      ],
      'tracking_code' => [
        'type' => 'string_textfield',
        'settings' => [],
      ],
    ];
  }

}
