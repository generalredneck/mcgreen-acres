<?php

namespace Drupal\webform_ip_geo\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Summary of SettingsForm.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Drupal\webform_ip_geo\Plugin\WebformIpGeoPluginManager definition.
   *
   * @var \Drupal\webform_ip_geo\Plugin\WebformIpGeoPluginManager
   */
  protected $pluginManagerWebformIpGeoPlugin;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->pluginManagerWebformIpGeoPlugin = $container->get('plugin.manager.webform_ip_geo_plugin');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'webform_ip_geo.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $providerOptions = $this->getProviderOptions();

    if (empty($providerOptions)) {
      $this->messenger()->addError('You need to install at least on API provider module for Webform IP Geo to work!');
      return $form;
    }

    $config = $this->config('webform_ip_geo.settings');
    $apiProviderKey = $config->get('api_provider');
    $form['api_provider'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#multiple' => FALSE,
      '#title' => $this->t('API Provider'),
      '#description' => $this->t('Select the API provider to get the IP based geo data from. You can add your own provider via a custom plugin.'),
      '#options' => $providerOptions,
      '#default_value' => $apiProviderKey,
    ];

    if (!empty($apiProviderKey)) {
      /** @var \Drupal\webform_ip_geo\Plugin\WebformIpGeoPluginInterface $providerPlugin */
      $providerPlugin = $this->pluginManagerWebformIpGeoPlugin->createInstance($apiProviderKey);

      $form['api_tokens'] = [
        '#type'        => 'fieldset',
        '#title'       => $this->t('Available API Tokens:'),
        '#description' => $providerPlugin->getTokenList(),
      ];

    }

    $form['debug_mode'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Debug Mode'),
      '#default_value' => $config->get('debug_mode'),
    ];

    $form['debug_ip'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Debug IP'),
      '#description'   => $this->t('Instead of determining the webform
      submission IP it will use this static set IP. This can be useful for local
      development environments that return restricted IPs'),
      '#default_value' => $config->get('debug_ip'),
      '#states'        => [
        'visible' => [
          ':input[name="debug_mode"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('webform_ip_geo.settings')
      ->set('api_provider', $form_state->getValue('api_provider'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->set('debug_ip', $form_state->getValue('debug_ip'))
      ->save();
  }

  /**
   * Retrieves all available provider plugins.
   *
   * @return array
   *   All available provider options.
   */
  private function getProviderOptions() {
    $plugins = $this->pluginManagerWebformIpGeoPlugin->getDefinitions();
    $options = [];
    foreach ($plugins as $plugin) {
      $options[$plugin['id']] = $plugin['label'];
    }

    return $options;
  }

}
