<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Filter by actions.
 *
 * @ingroup simplenews_stats
 *
 * @ViewsFilter("simplenews_stats_action")
 */
class SimplenewsStatsAction extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);
    // Force operator to IN.
    $this->operator = 'IN';
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value'] = [
      '#type' => 'select',
      '#options' => [
        'click' => $this->t('Click'),
        'view' => $this->t('View'),
      ],
    ];
  }

}
