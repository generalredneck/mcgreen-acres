<?php

namespace Drupal\evercurrent;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Site\Settings;
use Drupal\update\UpdateFetcherInterface;
use Drupal\update\UpdateManagerInterface;

/**
 * Default UpdateHelper instantiation.
 *
 * @package Drupal\evercurrent
 */
class UpdateHelper implements UpdateHelperInterface {

  /**
   * Configuration Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Module Handler service.
   *
   * @var Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Theme Handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Messenger Service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactory $configFactory, ModuleHandlerInterface $moduleHandler, ThemeHandlerInterface $themeHandler, MessengerInterface $messenger) {
    $this->configFactory = $configFactory;
    $this->moduleHandler = $moduleHandler;
    $this->themeHandler = $themeHandler;
    $this->messenger = $messenger;
  }

  /**
   * Send updates to the Maintenance server.
   *
   * @param bool $force
   *   Force Drupal's update collector to refresh available updates.
   * @param string $key
   *   Use another key than what is saved.
   * @param bool $out
   *   Display error messages as screen messages.
   *
   * @return bool
   *   Success Status
   */
  public function sendUpdates($force = TRUE, $key = NULL, $out = FALSE) {
    $config = \Drupal::config('evercurrent.admin_config');
    if (!$key) {
      $key = $this->getKeyFromSettings();
    }
    $valid = $this->keyCheck($key);
    if (!$valid) {
      $this->writeStatus(RMH_STATUS_WARNING, 'RMH Update check not run. The key is not a vaild key. It should be a 32-digit string with only letters and numbers.', $out);
      return FALSE;
    }
    $data = [];
    if ($available = update_get_available(TRUE)) {
      module_load_include('inc', 'update', 'update.compare');
      $data = update_calculate_project_data($available);
    }

    // Module version.
    $version = \Drupal::service('extension.list.module')->getExtensionInfo('evercurrent');

    $sender_data = [
      'send_url' => $config->get('target_address'),
      'project_name' => $this->getEnvironmentUrl(),
      'key' => $key,
      'module_version' => $version['version'],
      'api_version' => 1,
      'updates' => [],
    ];
    $status_list = [
      UpdateManagerInterface::NOT_SECURE,
      UpdateManagerInterface::REVOKED,
      UpdateManagerInterface::NOT_SUPPORTED,
      UpdateManagerInterface::CURRENT,
      UpdateFetcherInterface::NOT_CHECKED,
      UpdateManagerInterface::NOT_CURRENT,
    ];

    foreach ($data as $module => $module_info) {
      if (in_array($module_info['status'], $status_list)) {
        $sender_data['updates'][$module] = $data[$module];
        // In some cases (like multisite installations),
        // modules on certain paths are considered unimportant.
        $sender_data['updates'][$module]['module_path'] = str_replace('/' . $module, '', drupal_get_path('module', $module));
      }
    }

    // Send active module data, to allow us to act on uninstalled modules.
    $enabled_modules = $this->moduleHandler->getModuleList();
    $sender_data['enabled'] = [];
    foreach ($enabled_modules as $enabled_key => $enabled_module) {
      $sender_data['enabled'][$enabled_key] = $enabled_key;
    }
    $enabled_themes = $this->themeHandler->listInfo();
    foreach ($enabled_themes as $enabled_key => $enabled_theme) {
      $sender_data['enabled'][$enabled_key] = $enabled_key;
    }
    // Retrieve active installation profile data.
    // We mark this as enabled send this if we are using an installation profile
    // that the Update Manager module also reports on. Otherwise, Evercurrent
    // will not tell us about updates for it.
    $install_profile = Settings::get('install_profile');
    if ($install_profile && in_array($install_profile, array_keys($sender_data['updates']))) {
      $sender_data['enabled'][$install_profile] = $install_profile;
    }

    // Expose hook to add anything else.
    $this->moduleHandler->alter('evercurrent_update_data', $sender_data);

    // Send the updates to the server.
    $path = $sender_data['send_url'] . RMH_URL;

    // Set up a request.
    /** @var \Drupal::httpClient $client */
    try {
      $response = \Drupal::httpClient()
        ->request('POST', $path, [
          'body' => json_encode($sender_data),
          'headers' => [
            'Content-Type' => 'application/json',
          ],
        ]);
    }
    catch (\Exception $e) {
      $this->writeStatus(RMH_STATUS_ERROR, 'When trying to reach the server URL, Drupal reported the followng connection error: ' . $e->getMessage(), $out);
      return FALSE;
    }
    $code = $response->getStatusCode();
    $body = (string) $response->getBody();
    if (!$response->getStatusCode() == 200) {
      $this->writeStatus(RMH_STATUS_ERROR, 'Error code ' . $code . ' when trying to post to ' . $path, $out);
      return FALSE;
    }
    else {
      // Check the response data, was it successful?
      $response_data = Json::decode($body);
      if ($response_data) {
        $saved = $response_data['saved'];
        if (!$saved) {
          $this->writeStatus(RMH_STATUS_ERROR, $response_data['message'], $out);
          return FALSE;
        }
        else {
          \Drupal::state()->set('evercurrent_last_run', time());
          $this->writeStatus(RMH_STATUS_OK, $response_data['message'], $out);
          // If successful, we want to reassure that listening mode is off.
          $this->disableListening();
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Checks to see if the key matches.
   *
   * @param string $key
   *   A key to check.
   *
   * @return bool
   *   Success status.
   */
  public function keyCheck($key) {
    return is_string($key) && preg_match(RMH_MD5_MATCH, $key);
  }

  /**
   * Retrieve a key from settings.php, or from variable.
   */
  public function getKeyFromSettings() {
    $config = \Drupal::config('evercurrent.admin_config');
    $override = $config->get('override');
    // Key from regular configuration.
    $config_key = $config->get('key');
    // Key from settings.php.
    $settings_key = Settings::get('evercurrent_environment_token', NULL);
    // If.
    return ($settings_key && !$override) ? $settings_key : $config_key;
  }

  /**
   * Disable listening mode.
   */
  public function disableListening() {
    $config = $this->configFactory->getEditable('evercurrent.admin_config');
    $config->set('listen', FALSE);
    $config->save();
  }

  /**
   * Saves a status message for the status page.
   *
   * @param string $severity
   *   One of status or error.
   * @param string $message
   *   The message you want to write.
   * @param bool $output
   *   Should we output this meessage to the user.
   */
  public function writeStatus($severity, $message, $output = FALSE) {
    $config = $this->configFactory->getEditable('evercurrent.admin_config');
    $message = Xss::filter($message);
    $state = \Drupal::state();
    $state->set('evercurrent_status_message', $message);
    $state->set('evercurrent_status', $severity);
    $config->save();

    // If error, also log to watchdog.
    if ($severity == RMH_STATUS_ERROR) {
      \Drupal::logger('evercurrent')->error($message);
    }
    // Output this to message.
    if ($output) {
      $alert_type = ($severity == RMH_STATUS_OK) ? 'status' : 'error';
      $this->messenger->addMessage($message, $alert_type);
    }
  }

  /**
   * Check a key and set it if valid.
   *
   * @param string $key
   *   The key to set.
   *
   * @return bool
   *   Success status.
   */
  public function setKey($key) {
    $config = $this->configFactory->getEditable('evercurrent.admin_config');
    if ($this->keyCheck($key)) {
      $config->set('key', $key);
      return TRUE;
    }
    $this->writeStatus(RMH_STATUS_ERROR, 'Failed to set RMH key. Key should be a 32-character string.');
    return FALSE;
  }

  /**
   * Test sending an update to the server.
   *
   * @param string $key
   *   The key to validate against.
   *
   * @return bool
   *   Success status.
   */
  public function testUpdate($key) {
    if (!$this->keyCheck($key)) {
      $this->writeStatus(RMH_STATUS_ERROR, 'Failed to set RMH key. Key should be a 32-character string.');
      return FALSE;
    }
    $config = $this->configFactory->getEditable('evercurrent.admin_config');
    $config->set('listen', FALSE);
    $config->set('key', $key);
    $this->writeStatus(RMH_STATUS_OK, 'Key was successfully received.');
    $this->sendUpdates(TRUE);
    return TRUE;
  }

  /**
   * Get interval since last try.
   *
   * @return string
   *   The interval since the last try.
   */
  public function lastRun() {
    $last = \Drupal::state()->get('evercurrent_last_run') ?: 0;
    if ($last > 0) {
      $last_time = \Drupal::service('date.formatter')->formatInterval(time() - $last);
    }
    else {
      $last_time = t('Never.');
    }
    return t('%last_time',
      ['%last_time' => $last_time]);
  }

  /**
   * Helper function.
   *
   * Get an environment URL and ship together with the results.
   * First we see if we have our own explicit variable set. This
   * is only used for this purpose, and it allows the module
   * to be flexible in terms of determining the correct environment.
   *
   * @return string
   *   The environment url this module is working on.
   */
  public function getEnvironmentUrl() {
    global $base_url;
    $settings = Settings::get('evercurrent_environment_url', NULL);
    return $settings ? $settings : $base_url;
  }

}
