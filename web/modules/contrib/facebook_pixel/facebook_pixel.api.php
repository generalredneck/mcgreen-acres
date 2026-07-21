<?php

/**
 * @file
 * API documentation for Facebook Pixel.
 */

/**
 * @addtogroup hooks
 *
 * Hooks that extend the Facebook Pixel module.
 */

/**
 * Alters the event array of data items to be pushed.
 *
 * Modules can implement hook_facebook_pixel_data_alter() to modify
 * data sent to Facebook Pixel for a specific event.
 *
 * Possible event:
 *  - Purchase
 *  - AddToCart
 *  - InitiateCheckout
 *  - CompleteRegistration
 *  - ViewContent
 *
 * @param array &$data
 *   By reference. An array of all encoded data elements.
 * @param string $event
 *   Associated event id.
 */
function hook_facebook_pixel_event_data_alter(array &$data, $event) {
  // Replacing product sku(currently in the data) with Product ID.
  if ($event === 'ViewContent') {
    /** @var \Drupal\commerce_product\Entity\Product $entity */
    $entity = \Drupal::request()->get('_entity');
    $data['content_ids'][0] = $entity->id();
  }
}

/**
 * @} End of "addtogroup hooks".
 */
