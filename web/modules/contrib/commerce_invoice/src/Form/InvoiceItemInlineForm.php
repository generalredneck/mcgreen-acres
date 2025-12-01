<?php

namespace Drupal\commerce_invoice\Form;

use Drupal\inline_entity_form\Form\EntityInlineForm;

/**
 * Defines the inline form for invoice items.
 */
class InvoiceItemInlineForm extends EntityInlineForm {

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeLabels() {
    return [
      'singular' => $this->t('invoice item'),
      'plural' => $this->t('invoice items'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTableFields($bundles) {
    $fields = parent::getTableFields($bundles);
    $fields['quantity'] = [
      'type' => 'field',
      'label' => $this->t('Quantity'),
      'weight' => 2,
    ];
    $fields['unit_price'] = [
      'type' => 'field',
      'label' => $this->t('Unit price'),
      'weight' => 3,
    ];

    return $fields;
  }

}
