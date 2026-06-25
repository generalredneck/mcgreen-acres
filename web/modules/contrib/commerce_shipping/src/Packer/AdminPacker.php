<?php

namespace Drupal\commerce_shipping\Packer;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\ProposedShipment;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Provides a packer used to allow packing the order from the admin.
 *
 * This packer will return a ProposedShipment, regardless if the order has
 * items. It is defined with a very low priority to ensure other packers get a
 * chance to propose shipments.
 */
final class AdminPacker extends DefaultPacker implements PackerInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(OrderInterface $order, ProfileInterface $shipping_profile): bool {
    // This flag is set from the ShipmentAddModalForm.
    return $order->getData('commerce_shipping_admin_packer_applies', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function pack(OrderInterface $order, ProfileInterface $shipping_profile) {
    return [
      new ProposedShipment([
        'type' => $this->getShipmentType($order),
        'order_id' => $order->id(),
        'title' => $this->t('Shipment #1'),
        'shipping_profile' => $shipping_profile,
      ],
      ),
    ];
  }

}
