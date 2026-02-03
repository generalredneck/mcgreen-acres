<?php

namespace Drupal\commerce_fee;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Applies fees to orders during the order refresh process.
 */
class FeeOrderProcessor implements OrderProcessorInterface {

  /**
   * Constructs a new FeeOrderProcessor object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order): void {
    /** @var \Drupal\commerce_fee\FeeStorageInterface $fee_storage */
    $fee_storage = $this->entityTypeManager->getStorage('commerce_fee');
    $fees = $fee_storage->loadAvailable($order);
    foreach ($fees as $fee) {
      if ($fee->applies($order)) {
        $fee->apply($order);
      }
    }
  }

}
