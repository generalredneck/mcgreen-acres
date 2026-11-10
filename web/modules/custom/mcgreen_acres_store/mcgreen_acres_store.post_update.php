<?php

/**
 * @file
 * Post update functions for mcgreen_acres_store.
 */

use Drupal\commerce_fee\Entity\Fee;
use Drupal\pathauto\PathautoState;

/**
 * Create the credit card processing fee entity.
 */
function mcgreen_acres_store_post_update_create_credit_card_processing_fee(): void {
  $uuid = 'ef76db0f-cf33-48f6-8ef2-b6b66cfe132b';

  $existing = \Drupal::entityQuery('commerce_fee')
    ->accessCheck(FALSE)
    ->condition('uuid', $uuid)
    ->execute();

  if ($existing) {
    return;
  }

  $store = \Drupal::entityQuery('commerce_store')
    ->accessCheck(FALSE)
    ->execute();

  if (!$store) {
    return;
  }

  Fee::create([
    'uuid' => $uuid,
    'name' => 'Farm Support Contribution',
    'display_name' => 'Support the Farm',
    'description' => 'Optional customer contribution (2.9% + $0.30) to offset card processing costs. Worded as a voluntary tip rather than a surcharge, since Texas law (Tex. Bus. & Com. Code §604A.002) prohibits credit card surcharges.',
    'order_types' => ['default'],
    'stores' => array_keys($store),
    'plugin' => [
      'target_plugin_id' => 'credit_card_processing_fee',
      'target_plugin_configuration' => [],
    ],
    'condition_operator' => 'AND',
    'start_date' => '2026-05-10T00:00:00',
    'status' => TRUE,
  ])->save();
}

/**
 * Turn the farmstand flag off on products where it was never set.
 *
 * Products created before the field existed have no value stored, which the
 * catalog's "Farmstand" sort treats as lower than an explicit "off". Only
 * products with no value are touched, so existing farmstand products keep
 * their flag.
 */
function mcgreen_acres_store_post_update_farmstand_flag_default_off(array &$sandbox): void {
  $field_name = 'field_farmstand_available';
  $storage = \Drupal::entityTypeManager()->getStorage('commerce_product');

  if (!isset($sandbox['ids'])) {
    $field_map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('boolean');
    if (empty($field_map['commerce_product'][$field_name])) {
      $sandbox['ids'] = [];
    }
    else {
      $sandbox['ids'] = array_values($storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', array_keys($field_map['commerce_product'][$field_name]['bundles']), 'IN')
        ->notExists($field_name)
        ->execute());
    }
    $sandbox['total'] = count($sandbox['ids']);
  }

  /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
  foreach ($storage->loadMultiple(array_splice($sandbox['ids'], 0, 10)) as $product) {
    $product->set($field_name, FALSE)->save();
  }

  $sandbox['#finished'] = $sandbox['total'] ? 1 - count($sandbox['ids']) / $sandbox['total'] : 1;
}

/**
 * Generate URL aliases for products that never got one.
 *
 * A product saved while no Pathauto pattern covered its type (for example the
 * feed products created before the feed type was added to the Products
 * pattern) is stored with Pathauto set to "skip", and later pattern changes do
 * not revisit it. Products that already have an alias, including hand-set
 * ones, are left alone.
 */
function mcgreen_acres_store_post_update_generate_missing_product_aliases(): void {
  $entity_type_manager = \Drupal::entityTypeManager();
  $alias_storage = $entity_type_manager->getStorage('path_alias');

  /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
  foreach ($entity_type_manager->getStorage('commerce_product')->loadMultiple() as $product) {
    if ($alias_storage->loadByProperties(['path' => '/product/' . $product->id()])) {
      continue;
    }
    $product->get('path')->pathauto = PathautoState::CREATE;
    $product->save();
  }
}
