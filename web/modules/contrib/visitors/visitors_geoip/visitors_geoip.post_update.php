<?php

/**
 * @file
 * Post-update functions for the Visitors GeoIP module.
 */

declare(strict_types=1);

use Drupal\Core\Utility\Error;

/**
 * Fixes comma separator on visitors_id in the hit-details link display.
 *
 * The numeric field handler formats visitors_id with a thousands separator
 * (",") by default. Views token replacement uses the rendered value, so IDs
 * >= 1,000 produce URLs such as /visitors/hits/2%2C011 which cannot be
 * resolved by the route. This update sets separator to '' on the hidden
 * visitors_id field in the recent_view_table display so the raw integer is
 * substituted into the link path.
 */
function visitors_geoip_post_update_fix_visitors_id_separator(): string {
  $view_storage = \Drupal::entityTypeManager()->getStorage('view');
  $view = $view_storage->load('visitors_geoip');

  if ($view === NULL) {
    return 'Skipped: views.view.visitors_geoip not found.';
  }

  $displays = $view->get('display');

  if (!isset($displays['recent_view_table']['display_options']['fields']['visitors_id'])) {
    return 'Skipped: recent_view_table visitors_id field not found.';
  }

  $field = &$displays['recent_view_table']['display_options']['fields']['visitors_id'];

  if (($field['separator'] ?? ',') === '') {
    return 'No changes needed: separator already empty.';
  }

  $field['separator'] = '';

  $view->set('display', $displays);

  try {
    $view->save();
  }
  catch (\Exception $e) {
    Error::logException(\Drupal::logger('visitors'), $e);
    return 'Error saving view: ' . $e->getMessage();
  }

  return 'Fixed visitors_id separator in recent_view_table display.';
}
