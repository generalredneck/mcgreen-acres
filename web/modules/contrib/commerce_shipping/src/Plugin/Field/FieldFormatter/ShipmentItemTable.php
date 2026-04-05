<?php

namespace Drupal\commerce_shipping\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'commerce_shipment_item_table' formatter.
 */
#[FieldFormatter(
  id: 'commerce_shipment_item_table',
  label: new TranslatableMarkup('Shipment items table'),
  field_types: ['commerce_shipment_item'],
)]
class ShipmentItemTable extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $table = [
      '#type' => 'table',
      '#header' => [
        'label' => $this->t('Title'),
        'quantity' => $this->t('Quantity'),
        'amount' => $this->t('Amount'),
      ],
    ];

    foreach ($items->getShipmentItems() as $delta => $item) {
      /** @var \Drupal\commerce_shipping\ShipmentItem $item */

      $table[$delta] = [
        'label' => ['#markup' => $item->getTitle()],
        'quantity' => ['#markup' => $item->getQuantity()],
        'amount' => ['#markup' => $item->getDeclaredValue()],
      ];
    }

    return [0 => $table];
  }

}
