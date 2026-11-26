<?php

namespace Drupal\mcgreen_square_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mcgreen_square_sync\SaleImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for the Square sync.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The importer, which owns the stock date.
   */
  protected SaleImporter $importer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = parent::create($container);
    $form->importer = $container->get('mcgreen_square_sync.importer');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcgreen_square_sync_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['mcgreen_square_sync.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('First sale date'),
      '#description' => $this->t('Sales before this date are not fetched or shown.'),
      '#config_target' => 'mcgreen_square_sync.settings:start_date',
      '#required' => TRUE,
    ];
    $form['match_window_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Days between a sale and its order'),
      '#description' => $this->t('How far apart a Square sale and a Drupal order can be dated and still be suggested as a match.'),
      '#config_target' => 'mcgreen_square_sync.settings:match_window_days',
      '#min' => 0,
      '#max' => 14,
      '#required' => TRUE,
    ];
    $form['environment'] = [
      '#type' => 'select',
      '#title' => $this->t('Square environment'),
      '#options' => [
        'production' => $this->t('Production'),
        'sandbox' => $this->t('Sandbox'),
      ],
      '#config_target' => 'mcgreen_square_sync.settings:environment',
    ];
    $form['access_token_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Access token key'),
      '#description' => $this->t('The token must belong to the environment selected above.'),
      '#config_target' => 'mcgreen_square_sync.settings:access_token_key',
      '#required' => TRUE,
    ];
    // Kept in state, not config: each environment goes live on its own date.
    $form['stock_since'] = [
      '#type' => 'date',
      '#title' => $this->t('Deduct stock for sales from'),
      '#description' => $this->t('Importing an older sale leaves stock alone. This is not part of exported configuration.'),
      '#default_value' => $this->importer->getStockSince(),
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->importer->setStockSince($form_state->getValue('stock_since'));
    parent::submitForm($form, $form_state);
  }

}
