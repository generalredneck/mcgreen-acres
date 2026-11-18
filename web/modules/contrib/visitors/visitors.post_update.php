<?php

/**
 * @file
 * Post-update functions for the Visitors module.
 */

declare(strict_types=1);

use Drupal\Core\Utility\Error;

/**
 * Fixes comma separator on visitors_id in hit-details link displays.
 *
 * The numeric field handler formats visitors_id with a thousands separator
 * (",") by default. Views token replacement uses the rendered value, so IDs
 * >= 1,000 produce URLs such as /visitors/hits/2%2C011 which cannot be
 * resolved by the route. This update sets separator to '' on the hidden
 * visitors_id field in the recent_view_table and referrer_table displays so
 * the raw integer is substituted into the link path.
 */
function visitors_post_update_fix_visitors_id_separator(): string {
  $view_storage = \Drupal::entityTypeManager()->getStorage('view');
  $view = $view_storage->load('visitors');

  if ($view === NULL) {
    return 'Skipped: views.view.visitors not found.';
  }

  $displays = $view->get('display');

  $affected = [
    'recent_view_table',
    'referrer_table',
  ];

  $changed = FALSE;
  foreach ($affected as $display_id) {
    if (!isset($displays[$display_id]['display_options']['fields']['visitors_id'])) {
      continue;
    }

    $field = &$displays[$display_id]['display_options']['fields']['visitors_id'];

    if (($field['separator'] ?? ',') === '') {
      continue;
    }

    $field['separator'] = '';
    $changed            = TRUE;
  }

  if (!$changed) {
    return 'No changes needed: separator already empty.';
  }

  $view->set('display', $displays);

  try {
    $view->save();
  }
  catch (\Exception $e) {
    Error::logException(\Drupal::logger('visitors'), $e);
    return 'Error saving view: ' . $e->getMessage();
  }

  return 'Fixed visitors_id separator in recent_view_table and referrer_table displays.';
}
