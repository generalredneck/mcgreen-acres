<?php

namespace Drupal\errorlog\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a setting UI for errorlog.
 */
class ErrorlogConfigForm extends ConfigFormBase {

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['errorlog.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'errorlog_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('errorlog.settings');
    $form['errorlog'] = [
      '#type' => 'details',
      '#title' => $this->t('Error logging for each severity level.'),
      '#description' => $this->t('Check each severity level you want to get logged to the error log.'),
      '#open' => TRUE,
    ];
    foreach (RfcLogLevel::getLevels() as $severity => $description) {
      $key = 'errorlog_' . $severity;
      $form['errorlog'][$key] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Severity: @description', ['@description' => Unicode::ucfirst($description->render())]),
        '#default_value' => $config->get($key) ?: FALSE,
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('errorlog.settings');

    // A browser does not send an unchecked checkbox. Use the form values, not
    // the user input.
    foreach (RfcLogLevel::getLevels() as $severity => $description) {
      $key = 'errorlog_' . $severity;
      $config->set($key, (bool) $form_state->getValue($key));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
