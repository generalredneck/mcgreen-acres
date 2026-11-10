<?php

namespace Drupal\mcgreen_by_weight_orders\Plugin\Commerce\Condition;

use Drupal\commerce\Attribute\CommerceCondition;
use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\commerce_product\Plugin\Commerce\Condition\VariationTypeTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Matches orders that contain none of the selected variation types.
 */
#[CommerceCondition(
  id: "order_excludes_variation_type",
  label: new TranslatableMarkup("Product variation type (excluded)"),
  entity_type: "commerce_order",
  display_label: new TranslatableMarkup("Order contains NO product variation of these types"),
  category: new TranslatableMarkup("Products"),
)]
class OrderExcludesVariationType extends ConditionBase {

  use VariationTypeTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    foreach ($order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if ($purchased_entity && $purchased_entity->getEntityTypeId() === 'commerce_product_variation'
        && in_array($purchased_entity->bundle(), $this->configuration['variation_types'])) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
