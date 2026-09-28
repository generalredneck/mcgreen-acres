<?php

declare(strict_types=1);

namespace Drupal\optimize_database_tables\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\optimize_database_tables\Service\ConnectionRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for the Optimize DB module.
 *
 * Allows administrators to choose:
 *  - Whether to optimize all tables or a selected subset.
 *  - Which database connections to include (when optimizing all tables).
 *  - Which specific tables to target (when not optimizing all tables),
 *    displayed in optgroups per connection.
 */
class OptimizeDatabaseTablesConfigForm extends ConfigFormBase {

  /**
   * Configuration object name used by the module settings.
   */
  public const string OPTIMIZE_DATABASE_SETTINGS = 'optimize_database_tables.settings';

  /**
   * The connection registry for discovering available connections and tables.
   *
   * @var \Drupal\optimize_database_tables\Service\ConnectionRegistry
   */
  protected ConnectionRegistry $connectionRegistry;

  /**
   * Constructs the configuration form for Optimize DB.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed configuration manager service.
   * @param \Drupal\optimize_database_tables\Service\ConnectionRegistry $connectionRegistry
   *   The connection registry for listing available connections and tables.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    ConnectionRegistry $connectionRegistry,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->connectionRegistry = $connectionRegistry;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    /** @phpstan-ignore new.static (ConfigFormBase requires new static() for correct subclass instantiation) */
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('optimize_database_tables.connection_registry'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'optimize_database_tables_config_form';
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
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $config = $this->config(self::OPTIMIZE_DATABASE_SETTINGS);

    $availableKeys = $this->connectionRegistry->getAvailableConnectionKeys();

    $form['all_tables'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Optimize all tables'),
      '#default_value' => $config->get('all_tables'),
      '#description' => $this->t('Optimize all tables across the selected connections.'),
    ];

    // Build connection checkboxes (visible when "all tables" is checked).
    $connectionOptions = [];
    foreach ($availableKeys as $key) {
      $driver = $this->connectionRegistry->getDriverForKey($key);
      $connectionOptions[$key] = $key . ' (' . $driver . ')';
    }

    $savedConnections = $config->get('connections');
    $defaultConnections = is_array($savedConnections) ? $savedConnections : ['default'];

    $form['connections'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Connections to optimize'),
      '#options' => $connectionOptions,
      '#default_value' => $defaultConnections,
      '#description' => $this->t('Select which database connections to include when optimizing all tables.'),
      '#states' => [
        'visible' => [
          ':input[name="all_tables"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Build optgroup table select (visible when "all tables" is NOT checked).
    $tableOptions = [];
    $groupedTables = $this->connectionRegistry->getAllTablesGrouped($availableKeys);
    foreach ($groupedTables as $key => $tables) {
      $driver = $this->connectionRegistry->getDriverForKey($key);
      $groupLabel = $key . ' (' . $driver . ')';
      $tableOptions[$groupLabel] = $tables;
    }

    $form['table_list'] = [
      '#type' => 'select',
      '#title' => $this->t('Tables in the database'),
      '#options' => $tableOptions,
      '#default_value' => $config->get('table_list'),
      '#description' => $this->t('Selected tables will be optimized. Table IDs are prefixed with their connection key.'),
      '#multiple' => TRUE,
      '#attributes' => [
        'size' => 17,
      ],
      '#states' => [
        'visible' => [
          ':input[name="all_tables"]' => ['checked' => FALSE],
        ],
        'required' => [
          ':input[name="all_tables"]' => ['checked' => FALSE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * Validates that either "optimize all tables" is checked, or at least one
   * connection / table is selected. The #states required constraint is
   * client-side only; this prevents a bypass via direct POST.
   *
   * @param array<string, mixed> $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $allTables = (bool) $form_state->getValue('all_tables');

    if ($allTables) {
      $rawConnections = $form_state->getValue('connections', []);
      $connections = array_filter(is_array($rawConnections) ? $rawConnections : []);
      if (empty($connections)) {
        $form_state->setErrorByName(
          'connections',
          $this->t('Select at least one connection when optimizing all tables.')
        );
      }
    }
    else {
      $tableList = $form_state->getValue('table_list', []);
      if (empty($tableList)) {
        $form_state->setErrorByName(
          'table_list',
          $this->t('Select at least one table to optimize.')
        );
      }
    }
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
    $config = $this->config(self::OPTIMIZE_DATABASE_SETTINGS);

    // Checkboxes return [key => key|0]; filter keeps only checked values.
    $rawConnections = $form_state->getValue('connections', []);
    $connections = array_values(array_filter(
      is_array($rawConnections) ? $rawConnections : []
    ));

    $config
      ->set('all_tables', $form_state->getValue('all_tables'))
      ->set('connections', $connections)
      ->set('table_list', $form_state->getValue('table_list'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   The list of configuration object names this form manages.
   */
  protected function getEditableConfigNames(): array {
    return [static::OPTIMIZE_DATABASE_SETTINGS];
  }

}
