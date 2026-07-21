<?php

namespace Drupal\commerce_reports\Commands;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\commerce_reports\Form\OrderReportGenerateForm;
use Drush\Commands\DrushCommands;

/**
 * Commands for commerce reports.
 */
class CommerceReportsCommands extends DrushCommands {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Generates commerce reports.
   *
   * @param string|null $plugin_id
   *   Plugin id or null to generate all reports.
   *
   * @usage commerce_reports:generate-reports order_items_report
   *
   * @command commerce_reports:generate-reports
   * @aliases crgr
   */
  public function generateReports(?string $plugin_id = NULL) {
    $batch = [
      'title' => $this->t('Generating reports'),
      'progress_message' => '',
      'operations' => [
        [
          [OrderReportGenerateForm::class, 'processBatch'],
          [$plugin_id],
          $this->t('Generating reports'),
        ],
      ],
      'finished' => [$this, 'finishBatch'],
    ];
    batch_set($batch);
    drush_backend_batch_process();
  }

}
