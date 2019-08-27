<?php

namespace Drupal\mcgreen_acres_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Component\Utility\SafeMarkup;

/**
 * Provides a 'SearchBlock' block.
 *
 * @Block(
 *  id = "search_block",
 *  admin_label = @Translation("Search Block"),
 * )
 */
class SearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['#theme'] = 'search_block';
    $build['#search_value'] = !empty($_GET['search_api_fulltext']) ? SafeMarkup::checkPlain($_GET['search_api_fulltext']) : '';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
