<?php

/**
 * @file
 * Contains views hooks.
 */

/**
 * Implements hook_views_data_alter().
 */
function file_to_media_views_data_alter(&$data) {
  $data['file_managed']['file_to_media'] = [
    'title' => t('File to Media links'),
    'help' => t('Links to create a media item from this file.'),
    'real field' => 'fid',
    'field' => [
      'id' => 'file_to_media',
    ],
  ];
}
