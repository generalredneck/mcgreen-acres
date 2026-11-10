<?php

namespace Drupal\mcgreen_by_weight_orders\Access;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\mcgreen_by_weight_orders\ByWeightOrderCharger;

/**
 * Restricts the weigh-and-charge screen to orders with by-weight items.
 */
class ByWeightOrderAccess {

  public function __construct(protected ByWeightOrderCharger $charger) {}

  /**
   * Allows access only when the order contains a by-weight item.
   */
  public function access(OrderInterface $commerce_order): AccessResultInterface {
    return AccessResult::allowedIf((bool) $this->charger->getByWeightItems($commerce_order))
      ->addCacheableDependency($commerce_order);
  }

}
