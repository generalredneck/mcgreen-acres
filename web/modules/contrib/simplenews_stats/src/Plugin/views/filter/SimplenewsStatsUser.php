<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filter by user (owner).
 *
 * @ingroup simplenews_stats
 *
 * @ViewsFilter("simplenews_stats_user")
 */
class SimplenewsStatsUser extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value'] = [
      '#type' => 'textfield',
      '#autocomplete_route_name' => 'simplenews_stats.user_autocomplete',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $value = $this->massageValue();
    if (!$value) {
      return;
    }

    $this->ensureMyTable();
    $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $value, $this->operator);
  }

  /**
   * Extract entity id and entity type form the string value.
   */
  protected function massageValue() {
    preg_match('/\((\d+)\)$/', $this->value[0], $matches);

    if (count($matches) != 2) {
      return FALSE;
    }
    return $matches[1];
  }

}
