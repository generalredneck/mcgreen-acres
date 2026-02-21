<?php

/**
 * @file
 * Contains views hooks for simplenews_stats.
 */

declare(strict_types=1);

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Implements hook_views_data_alter().
 */
function simplenews_stats_views_data_alter(array &$data) {
  // Simplenews stats alter.
  if (!empty($data['simplenews_stats'])) {
    // Add Entity associated.
    $data['simplenews_stats']['entity_associated'] = [
      'title' => new TranslatableMarkup('Entity associated'),
      'help' => new TranslatableMarkup('The entity associated to the simplenews stats entity.'),
      'field' => [
        'id' => 'simplenews_stats_entity_associated',
      ],
      'filter' => [
        'id' => 'simplenews_stats_entity_associated',
      ],
      'entity field' => 'entity_id',
      'real field' => 'entity_id',
    ];
  }

  // Simplenews stats item alter.
  if (!empty($data['simplenews_stats_item'])) {
    // Switch the plugin to add a choice list.
    $data['simplenews_stats_item']['title']['filter']['id'] = 'simplenews_stats_action';

    // Switch the plugin to a custom autocomplete one.
    $data['simplenews_stats_item']['uid']['filter']['id'] = 'simplenews_stats_user';

    // Add Entity associated.
    $data['simplenews_stats_item']['entity_associated'] = [
      'title' => new TranslatableMarkup('Entity associated'),
      'help' => new TranslatableMarkup('The entity associated to the item.'),
      'field' => [
        'id' => 'simplenews_stats_entity_associated',
      ],
      'filter' => [
        'id' => 'simplenews_stats_entity_associated',
      ],
      'entity field' => 'entity_id',
      'real field' => 'entity_id',
    ];
  }
}
