<?php

namespace Drupal\mcgreen_square_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings for the Square sync.
 */
class SettingsForm extends ConfigFormBase {

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
    return parent::buildForm($form, $form_state);
  }

}
