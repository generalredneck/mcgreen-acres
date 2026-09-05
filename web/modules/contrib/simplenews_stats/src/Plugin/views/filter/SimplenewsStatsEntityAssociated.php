<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filter by entity associated.
 *
 * @ingroup simplenews_stats
 *
 * @ViewsFilter("simplenews_stats_entity_associated")
 */
class SimplenewsStatsEntityAssociated extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value'] = [
      '#type'                    => 'textfield',
      '#autocomplete_route_name' => 'simplenews_stats.entity_associated_autocomplete',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $values = $this->massageValue();
    if (!$values) {
      return;
    }

    $this->ensureMyTable();
    $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $values['entity_id'], $this->operator);
    $this->query->addWhere($this->options['group'], "$this->tableAlias.entity_type", $values['entity_type'], $this->operator);
  }

  /**
   * Extract entity id and entity type form the string value.
   */
  protected function massageValue() {
    preg_match('/\(([a-zA-Z_]*)\|(\d+)\)$/', $this->value[0], $matches);

    if (count($matches) != 3) {
      return FALSE;
    }
    return [
      'entity_type' => $matches[1],
      'entity_id'   => $matches[2],
    ];
  }

}
