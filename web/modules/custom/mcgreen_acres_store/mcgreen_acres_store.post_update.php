<?php

/**
 * @file
 * Post update functions for mcgreen_acres_store.
 */

use Drupal\commerce_fee\Entity\Fee;

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
    'name' => 'Credit Card Processing Fee',
    'display_name' => 'Credit Card Processing Fee',
    'description' => 'Covers the 2.9% + $0.30 Stripe processing fee so the full amount goes to the farm.',
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
