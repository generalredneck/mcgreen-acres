<?php

/**
 * @file
 * Hooks provided by the responsive_favicons module.
 */

use Drupal\Core\Config\Config;

/**
 * Allow other modules to alter the loaded tags.
 *
 * @param array $tags
 *   The tags loaded by the responsive_favicons module.
 */
function hook_responsive_favicons_tags_alter(array &$tags) {
}

/**
 * Allow other modules to alter the normalised icon path.
 *
 * @param string $icon_path
 *   The normalised icon path.
 * @param \Drupal\Core\Config\Config $config
 *   The module configuration that might not have been saved yet.
 */
function hook_responsive_favicons_icon_path_alter(string &$icon_path, Config $config) {
}
