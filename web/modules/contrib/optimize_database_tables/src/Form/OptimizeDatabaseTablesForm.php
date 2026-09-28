<?php

declare(strict_types=1);

namespace Drupal\optimize_database_tables\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\optimize_database_tables\Service\OptimizeDatabase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to trigger optimization of selected database tables.
 *
 * Displays a confirmation with the target tables and submits to start
 * a batch process invoking the optimization service.
 */
class OptimizeDatabaseTablesForm extends FormBase {

  /**
   * Constructs the form with the optimization service.
   *
   * @param \Drupal\optimize_database_tables\Service\OptimizeDatabase $optimizeDatabase
   *   Service that orchestrates database table optimization via batches.
   */
  public function __construct(
    private readonly OptimizeDatabase $optimizeDatabase,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('optimize_database_tables.service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'optimize_database_tables_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array<string, mixed>
   *   The complete form render array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(OptimizeDatabaseTablesConfigForm::OPTIMIZE_DATABASE_SETTINGS);

    $allTables = $config->get('all_tables');
    $tables = $config->get('table_list');

    if ($allTables) {
      $message = new FormattableMarkup(
        'All Tables in Database will be optimized',
        []
      );
    }
    else {
      $message = new FormattableMarkup(
        'Only those tables in the database: @tables will be optimized',
        [
          '@tables' => implode(', ', is_array($tables) ? $tables : []),
        ]
      );
    }
    $form['status'] = [
      '#theme' => 'status_messages',
      '#status_headings' => [
        'info' => $this->t('Optimize Database Tables'),
        'warning' => $this->t('Warning'),
      ],
      '#message_list' => [
        'info' => [
          'message' => $message,
        ],
        'warning' => [
          'message' => $this->t(
            'This action may take a long time to complete.<br />
                    Consider to use drush command <b><code>drush optimize_database_tables:run</code></b>'
          ),
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Optimize Database Tables'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $this->optimizeDatabase->optimizeDatabase();
  }

}
