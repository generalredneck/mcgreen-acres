<?php

namespace Drupal\custom_user_tokens\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Custom User Tokens settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_user_tokens_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['custom_user_tokens.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('custom_user_tokens.settings');

    $form['conditional_display_pattern'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Conditional display pattern'),
      '#description' => $this->t('Enter a pattern for the conditional display name. This can include tokens. If the result is empty, the user\'s email will be used instead. Available tokens: @tokens', [
        '@tokens' => '[user:name], [user:mail], [user:field_first_name], etc.',
      ]),
      '#default_value' => $config->get('conditional_display_pattern'),
      '#rows' => 3,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('custom_user_tokens.settings')
      ->set('conditional_display_pattern', $form_state->getValue('conditional_display_pattern'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
