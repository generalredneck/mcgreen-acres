<?php

/**
 * @file
 * Post update functions for By-weight orders.
 */

use Drupal\commerce_order\Entity\Order;

/**
 * Show packed weights on each order line instead of one combined adjustment.
 *
 * Totals are unchanged; an order whose total would change is skipped.
 */
function mcgreen_by_weight_orders_post_update_weights_on_order_lines(array &$sandbox) {
  $storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
  if (!isset($sandbox['ids'])) {
    $sandbox['ids'] = array_values(\Drupal::database()
      ->query("SELECT DISTINCT oi.entity_id FROM {commerce_order__order_items} oi INNER JOIN {commerce_order_item} i ON i.order_item_id = oi.order_items_target_id WHERE i.type = :type", [
        ':type' => 'by_weight',
      ])
      ->fetchCol());
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['converted'] = 0;
    $sandbox['skipped'] = [];
  }
  $charger = \Drupal::service('mcgreen_by_weight_orders.charger');
  foreach (array_splice($sandbox['ids'], 0, 10) as $id) {
    $order = Order::load($id);
    if (!$order) {
      continue;
    }
    try {
      $sandbox['converted'] += (int) $charger->convertLegacyAdjustments($order);
    }
    catch (\Exception $e) {
      $sandbox['skipped'][] = $id;
      \Drupal::logger('mcgreen_by_weight_orders')->error('Order @id was not converted: @message', [
        '@id' => $id,
        '@message' => $e->getMessage(),
      ]);
    }
  }
  $sandbox['#finished'] = $sandbox['ids'] ? 1 - count($sandbox['ids']) / $sandbox['total'] : 1;
  if ($sandbox['#finished'] >= 1) {
    return sprintf('Converted %d orders.%s', $sandbox['converted'], $sandbox['skipped'] ? ' Skipped: ' . implode(', ', $sandbox['skipped']) . '.' : '');
  }
}
