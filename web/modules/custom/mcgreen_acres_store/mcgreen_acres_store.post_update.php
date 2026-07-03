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
