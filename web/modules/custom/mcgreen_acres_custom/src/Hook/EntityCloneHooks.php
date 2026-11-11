<?php

namespace Drupal\mcgreen_acres_custom\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;

/**
 * Hook implementations that adapt entity_clone to Commerce routes.
 */
class EntityCloneHooks {

  /**
   * Implements hook_entity_type_alter().
   *
   * The entity_clone module sets every clone-form link template to
   * /entity_clone/{type}/{id}. Product variations have no canonical route, so
   * the "Clone" local task uses entity.commerce_product_variation.edit_form
   * as its base route, which requires {commerce_product}. The default clone
   * path lacks that placeholder, so rendering the tabs on the clone page
   * throws MissingMandatoryParametersException. Add the placeholder, as
   * Commerce does for its own duplicate-form template. Runs last so it
   * overrides entity_clone's implementation.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[] $entity_types
   *   The entity type definitions, keyed by entity type ID.
   */
  #[Hook('entity_type_alter', order: Order::Last)]
  public function entityTypeAlter(array &$entity_types): void {
    if (isset($entity_types['commerce_product_variation']) && $entity_types['commerce_product_variation'] instanceof EntityTypeInterface && $entity_types['commerce_product_variation']->hasLinkTemplate('clone-form')) {
      $entity_types['commerce_product_variation']->setLinkTemplate('clone-form', '/product/{commerce_product}/variations/{commerce_product_variation}/clone');
    }
  }

}
