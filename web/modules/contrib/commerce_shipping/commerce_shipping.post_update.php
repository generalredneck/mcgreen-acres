<?php

/**
 * @file
 * Post update functions for Shipping.
 */

use Drupal\Core\Entity\Entity\EntityFormMode;

/**
 * Re-save shipping methods to populate the condition operator field.
 */
function commerce_shipping_post_update_1(&$sandbox = NULL) {
  $shipping_method_storage = \Drupal::entityTypeManager()->getStorage('commerce_shipping_method');
  if (!isset($sandbox['current_count'])) {
    $query = $shipping_method_storage->getQuery();
    $query->accessCheck(FALSE);
    $sandbox['total_count'] = $query->count()->execute();
    $sandbox['current_count'] = 0;

    if (empty($sandbox['total_count'])) {
      $sandbox['#finished'] = 1;
      return;
    }
  }

  $query = $shipping_method_storage->getQuery();
  $query->accessCheck(FALSE);
  $query->range($sandbox['current_count'], 25);
  $result = $query->execute();
  if (empty($result)) {
    $sandbox['#finished'] = 1;
    return;
  }

  /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface[] $shipping_methods */
  $shipping_methods = $shipping_method_storage->loadMultiple($result);
  foreach ($shipping_methods as $shipping_method) {
    $shipping_method->setConditionOperator('AND');
    $shipping_method->save();
  }

  $sandbox['current_count'] += 25;
  if ($sandbox['current_count'] >= $sandbox['total_count']) {
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['total_count'] - $sandbox['current_count']) / $sandbox['total_count'];
  }
}

/**
 * Add workflow property to shipping method plugins.
 */
function commerce_shipping_post_update_2(&$sandbox = NULL) {
  $shipping_method_storage = \Drupal::entityTypeManager()->getStorage('commerce_shipping_method');
  if (!isset($sandbox['current_count'])) {
    $query = $shipping_method_storage->getQuery();
    $query->accessCheck(FALSE);
    $sandbox['total_count'] = $query->count()->execute();
    $sandbox['current_count'] = 0;

    if (empty($sandbox['total_count'])) {
      $sandbox['#finished'] = 1;
      return;
    }
  }

  $query = $shipping_method_storage->getQuery();
  $query->accessCheck(FALSE);
  $query->range($sandbox['current_count'], 25);
  $result = $query->execute();
  if (empty($result)) {
    $sandbox['#finished'] = 1;
    return;
  }

  /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface[] $shipping_methods */
  $shipping_methods = $shipping_method_storage->loadMultiple($result);
  foreach ($shipping_methods as $shipping_method) {
    // Work on the raw plugin item to avoid defaults being merged in.
    $plugin = $shipping_method->get('plugin')->first();
    $configuration = $plugin->target_plugin_configuration;
    if (!isset($configuration['workflow'])) {
      $configuration['workflow'] = 'shipment_default';
      $plugin->target_plugin_configuration = $configuration;
      $shipping_method->save();
    }
  }

  $sandbox['current_count'] += 25;
  if ($sandbox['current_count'] >= $sandbox['total_count']) {
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['total_count'] - $sandbox['current_count']) / $sandbox['total_count'];
  }
}

/**
 * Create the 'checkout' form/view mode and displays for the shipment entity.
 */
function commerce_shipping_post_update_3() {
  /** @var \Drupal\commerce\Config\ConfigUpdaterInterface $config_updater */
  $config_updater = \Drupal::service('commerce.config_updater');
  $result = $config_updater->import([
    'core.entity_form_mode.commerce_shipment.checkout',
    'core.entity_form_display.commerce_shipment.default.checkout',
    'core.entity_view_mode.commerce_shipment.checkout',
    'core.entity_view_display.commerce_shipment.default.checkout',
  ]);
  $message = implode('<br>', $result->getFailed());

  return $message;
}

/**
 * Create the "shipping" form mode for profiles.
 */
function commerce_shipping_post_update_4() {
  if (EntityFormMode::load('profile.shipping')) {
    return '';
  }

  /** @var \Drupal\commerce\Config\ConfigUpdaterInterface $config_updater */
  $config_updater = \Drupal::service('commerce.config_updater');
  $result = $config_updater->import([
    'core.entity_form_mode.profile.shipping',
  ]);
  $message = implode('<br>', $result->getFailed());

  return $message;
}

/**
 * Unmark shipment reference as deleted in the database.
 */
function commerce_shipping_post_update_5(array &$sandbox): void {
  if (!isset($sandbox['order_types'])) {
    $order_types = \Drupal::entityTypeManager()
      ->getStorage('commerce_order_type')
      ->loadMultiple();
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    foreach ($order_types as $order_type) {
      $shipment_type = $order_type->getThirdPartySetting('commerce_shipping', 'shipment_type');
      if ($shipment_type) {
        $sandbox['order_types'][] = $order_type->id();
      }
    }
  }

  // If none of the order types configured to have shipment, ship the update.
  if (empty($sandbox['order_types'])) {
    $sandbox['#finished'] = 1;
    return;
  }

  $table = 'commerce_order__shipments';
  $database = \Drupal::database();

  // Check if there is still reference to shipment that is marked as deleted.
  $deleted_shipments = $database->select($table, 'cos')
    ->fields('cos', ['shipments_target_id'])
    ->condition('bundle', $sandbox['order_types'], 'IN')
    ->condition('deleted', '1')
    ->range(0, 1000)
    ->orderBy('shipments_target_id')
    ->execute()->fetchCol();
  if (empty($deleted_shipments)) {
    $sandbox['#finished'] = 1;
    return;
  }
  $sandbox['#finished'] = 0;

  // Unmark shipment reference as deleted.
  $database->update($table)
    ->fields(['deleted' => '0'])
    ->condition('shipments_target_id', $deleted_shipments, 'IN')
    ->execute();
}

/**
 * Unmark shipment reference as deleted in the database.
 */
function commerce_shipping_post_update_6(array &$sandbox): void {
  // The previous post update function didn't update all rows, because it
  // considered it was done after updating the first 1000 rows.
  commerce_shipping_post_update_5($sandbox);
}
