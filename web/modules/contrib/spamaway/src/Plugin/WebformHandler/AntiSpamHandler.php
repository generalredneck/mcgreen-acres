<?php

namespace Drupal\spamaway\Plugin\WebformHandler;

use Drupal\Core\Site\Settings;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checks Anti SPAM of a submission.
 *
 * Checks if the current submission has been done before based on these criteria:
 * - within a specific period of time (configuratble)
 * - similar data has been posted for specific fields (based on similar_text percentage threshold)
 * - x amount of similar posts have been made.
 *
 * @WebformHandler(
 *   id = "spamaway_anti_spam_forms",
 *   label = @Translation("SpamAway - Anti spam handler"),
 *   category = @Translation("Anti-SPAM"),
 *   description = @Translation("SpamAway - Anti Spam based on repeated (similar) submissions."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class AntiSpamHandler extends WebformHandlerBase {

  const SPAMAWAY_SUBMISSION_TABLE = 'spamaway_webform_submission';

  /**
   * @var \Drupal\Core\Database\Connection $connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * Gets default configuration for this plugin.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  public function defaultConfiguration() {
    return [
      'spamaway_anti_spam_field_names' => 'message',
      'spamaway_anti_spam_hash' => 'sha256',
      'spamaway_anti_spam_threshold_percentage' => 80,
      'spamaway_anti_spam_period' => 0,
      'spamaway_anti_spam_allowed_count' => 5,
      'spamaway_anti_spam_ip_period' => 36000,
      'spamaway_anti_spam_allowed_ip_count' => 4,
      'spamaway_anti_spam_logging' => 0,
      'spamaway_query_limit' => 200,
      'spamaway_ip_check_enabled' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['spamaway_anti_spam_field_names'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field names'),
      '#description' => $this->t('A comma seperated list of field names to take into consideration for similarity. You can also add \'ip\' to check on IP address and combine fields using a + seperator. Ex: field_a,field_b+field_c,field_d+ip'),
      '#default_value' => $this->configuration['spamaway_anti_spam_field_names'] ??
        $this->defaultConfiguration['spamaway_anti_spam_field_names'],
    ];

    $form['spamaway_anti_spam_hash'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hash algorithm'),
      '#description' => $this->t('The hash algorithm used for storing field name values to check against. See php hash for supported algoritms. This is only used if the webform is not storing data itself.'),
      '#default_value' => $this->configuration['spamaway_anti_spam_hash'] ??
        $this->defaultConfiguration['spamaway_anti_spam_hash'],
    ];

    $form['spamaway_anti_spam_threshold_percentage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Threshold percentage'),
      '#suffix' => '%',
      '#description' => $this->t('A comma seperated list of threshold percentages for each field name (or one single value used for all field names). This is only use if the webform stores data itself.'),
      '#default_value' => $this->configuration['spamaway_anti_spam_threshold_percentage'] ??
        $this->defaultConfiguration['spamaway_anti_spam_threshold_percentage'],
    ];

    $form['spamaway_anti_spam_period'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Period of time'),
      '#description' => $this->t('The period for within similar submissions must have been submitted in seconds. Use 0(default) to disable this condition.'),
      '#suffix' => 'seconds',
      '#default_value' => $this->configuration['spamaway_anti_spam_period'] ??
        $this->defaultConfiguration['spamaway_anti_spam_period'],
    ];

    $form['spamaway_anti_spam_allowed_count'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed count'),
      '#description' => $this->t('The number of similar submissions allowed before we consider it spam. You can also use a comma seperated list of counts for each field name you specified.'),
      '#default_value' => $this->configuration['spamaway_anti_spam_allowed_count'] ??
        $this->defaultConfiguration['spamaway_anti_spam_allowed_count'],
    ];

    $form['spamaway_anti_spam_ip_period'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Period of time for IP'),
      '#description' => $this->t('The period for within submissions with the same IP address are considered spam in seconds. Defaults to 10 minutes'),
      '#suffix' => 'seconds',
      '#default_value' => $this->configuration['spamaway_anti_spam_ip_period'] ??
        $this->defaultConfiguration['spamaway_anti_spam_ip_period'],
    ];

    $form['spamaway_anti_spam_allowed_ip_count'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed IP count'),
      '#description' => $this->t('The number of similar submissions allowed by the same IP address before we consider it spam. Defaults to 4'),
      '#default_value' => $this->configuration['spamaway_anti_spam_allowed_ip_count'] ??
        $this->defaultConfiguration['spamaway_anti_spam_allowed_ip_count'],
    ];

    $form['spamaway_ip_check_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('IP check enabled'),
      '#description' => $this->t('Enable IP check validation.'),
      '#default_value' => $this->configuration['spamaway_ip_check_enabled'] ??
        $this->defaultConfiguration['spamaway_ip_check_enabled'],
    ];

    $form['spamaway_anti_spam_logging'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enable logging'),
      '#default_value' => $this->configuration['spamaway_anti_spam_logging'] ??
        $this->defaultConfiguration['spamaway_anti_spam_logging'],
    ];

    $form['spamaway_query_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Query limit'),
      '#description' => $this->t('Cannot be higher than 200'),
      '#default_value' => $this->configuration['spamaway_query_limit'] ??
        $this->defaultConfiguration['spamaway_query_limit'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['spamaway_anti_spam_field_names'] = $form_state->getValue('spamaway_anti_spam_field_names');
    $this->configuration['spamaway_anti_spam_hash'] = $form_state->getValue('spamaway_anti_spam_hash');
    $this->configuration['spamaway_anti_spam_period'] = $form_state->getValue('spamaway_anti_spam_period');
    $this->configuration['spamaway_anti_spam_allowed_count'] = $form_state->getValue('spamaway_anti_spam_allowed_count');
    $this->configuration['spamaway_anti_spam_threshold_percentage'] = $form_state->getValue('spamaway_anti_spam_threshold_percentage');
    $this->configuration['spamaway_anti_spam_ip_period'] = $form_state->getValue('spamaway_anti_spam_ip_period');
    $this->configuration['spamaway_anti_spam_allowed_ip_count'] = $form_state->getValue('spamaway_anti_spam_allowed_ip_count');
    $this->configuration['spamaway_anti_spam_logging'] = $form_state->getValue('spamaway_anti_spam_logging');
    $this->configuration['spamaway_query_limit'] = $form_state->getValue('spamaway_query_limit');
    $this->configuration['spamaway_ip_check_enabled'] = $form_state->getValue('spamaway_ip_check_enabled');
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {

    // Store the ip address into our custom table.
    if ($this->isIpCheckEnabled()) {
      $this->connection->insert(self::SPAMAWAY_SUBMISSION_TABLE)->fields([
        'webform_id' => $webform_submission->getWebform()->id(),
        'created' => $webform_submission->getCreatedTime(),
        'submission' => $webform_submission->serial(),
        'field_name' => 'ip',
        'value' => $webform_submission->getRemoteAddr()
      ])->execute();
    }

    // Store the submission fields we need into our custom table.
    if (!$this->isSaveSubmissionsEnabled()) {
      $field_names = $this->getFieldNames();
      foreach ($field_names as $field_name) {
        $value = $this->getFieldValue($field_name, ['ip' => $webform_submission->getRemoteAddr()] + $webform_submission->getData());
        if (!empty($value)) {
          $this->connection->insert(self::SPAMAWAY_SUBMISSION_TABLE)->fields([
            'webform_id' => $webform_submission->getWebform()->id(),
            'created' => $webform_submission->getCreatedTime(),
            'submission' => $webform_submission->serial(),
            'field_name' => $field_name,
            'value' => $value
          ])->execute();
        }
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function postDelete(WebformSubmissionInterface $webform_submission) {
    // Delete all entries from the custom table.
    $this->connection->delete(self::SPAMAWAY_SUBMISSION_TABLE)
      ->condition('webform_id', $webform_submission->getWebform()->id())
      ->condition('submission', $webform_submission->serial())
      ->execute();
  }

  /**
   * Helper to get an array of clean values from a string.
   *
   * @param string $seperator
   * @param string $array
   *
   * @return array
   */
  protected function explodeTrimmed($seperator, $string) {
    return array_filter(array_map('trim', explode($seperator, $string)));
  }

  /**
   * Get the field names from the config settings.
   *
   * @return array
   */
  protected function getFieldNames() {
    return $this->explodeTrimmed(',', $this->configuration['spamaway_anti_spam_field_names']);
  }

  /**
   * Returns the hashed value of a field name (or field key combo).
   */
  protected function getFieldValue($field_name, $data) {
    $value = '';

    // A field name can be a combination of fields using a +
    $keys = $this->explodeTrimmed('+', $field_name);
    foreach ($keys as $key) {
      if (!empty($data[$key])) {
        $value .= $this->hashValue($data[$key]);
      }
    }

    return $value;
  }

  protected function isSaveSubmissionsEnabled() {
    return ($this->getWebform()->getSetting('results_disabled') === FALSE);
  }

  protected function isLoggingEnabled() {
    return ($this->configuration['spamaway_anti_spam_logging'] ?? 0);
  }

  protected function isIpCheckEnabled() {
    return ($this->configuration['spamaway_ip_check_enabled'] ?? 0);
  }

  protected function hashValue($value) {
    $hashed = hash($this->configuration['spamaway_anti_spam_hash'], $value, false);
    return $hashed;
  }

  /**
   * Validate webform submission webform .
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $hasPermissionToBypass = \Drupal::currentUser()
      ->hasPermission('spamaway bypass spam detection');

    $globalSettingsBypass = Settings::get('spamaway_bypass_anti_spam', FALSE);

    if ($hasPermissionToBypass || $globalSettingsBypass) {
      if ($this->isLoggingEnabled()) {
        $this->getLogger('spamaway_spam')->debug($this->t('Spam detection was bypassed for @user', ['@user' => \Drupal::currentUser()->getAccountName()]));
      }
      return;
    }

    // Set hard limit
    if ($this->configuration['spamaway_query_limit'] > 200) {
      $this->configuration['spamaway_query_limit'] = 200;
    }

    ////////////////////////////////////////////////////////////////////////////
    // Make sure the last x seconds no submission was done by the same IP
    // address. Otherwise consider it spam.
    if ($this->isIpCheckEnabled()) {
      $this->baseIpCheck($webform_submission, $form_state);
    }

    if ($this->isSaveSubmissionsEnabled()) {
      $this->validateFormWithSavedSubmissions($form, $form_state, $webform_submission);
    } else {
      $this->validateFormCustomSubmissions($form, $form_state, $webform_submission);
    }

  }

  private function baseIpCheck(WebformSubmissionInterface $webform_submission, FormStateInterface $form_state) {
    $query_ip = $this->connection->select(self::SPAMAWAY_SUBMISSION_TABLE, 'w');
    $query_ip->condition('webform_id', $webform_submission->getWebform()->id());
    $query_ip->condition('field_name', 'ip');
    $query_ip->condition('value', $webform_submission->getRemoteAddr());
    $query_ip->where('created > UNIX_TIMESTAMP() - :limit', [':limit' => $this->configuration['spamaway_anti_spam_ip_period']]);
    $count_ip = $query_ip->countQuery()->execute()->fetchField();
    if ($count_ip > $this->configuration['spamaway_anti_spam_allowed_ip_count']) {
      $this->spamDetected($form_state, $this->t('Spam detected by IP check on @webform from @ip within @period (count @count / @allowed)', [
        '@webform' => $webform_submission->getWebform()->id(),
        '@ip' => $webform_submission->getRemoteAddr(),
        '@period' => $this->configuration['spamaway_anti_spam_ip_period'],
        '@count' => $count_ip,
        '@allowed' => $this->configuration['spamaway_anti_spam_allowed_ip_count']
      ]));
    }
  }

  /**
   * Validate webform submission webform .
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   */
  protected function validateFormWithSavedSubmissions(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    ////////////////////////////////////////////////////////////////////////////
    // Check if the submitted message is similar to previous submitted messages

    // Lookup X last submissions.
    $query = $this->connection->select('webform_submission', 's');
    $query->addField('d', 'value', 'value');
    $query->addField('d', 'name', 'name');
    $query->leftJoin('webform_submission_data', 'd', 'd.sid = s.sid');
    $query->range(0, $this->configuration['spamaway_query_limit']);
    $query->orderBy('s.created', 'DESC');

    $period = time() - $this->configuration['spamaway_anti_spam_period'];
    if ($this->configuration['spamaway_anti_spam_period'] && $period > 0) {
      $query->condition('s.created', $period, '>');
    }

    // Get the submitted values and while we are at it,
    // also create the condition statements.
    $field_conditions = $query->orConditionGroup();
    $form_values = [];
    $names = explode(',', $this->configuration['spamaway_anti_spam_field_names']);
    $thresholds = explode(',', $this->configuration['spamaway_anti_spam_threshold_percentage']);

    foreach ($names as $index => $name) {
      $form_values[$name] = $form_state->getValue($name);
      $field_conditions->condition('d.name', $name);
      $thresHoldsNumber = count($thresholds);

      if ($thresHoldsNumber === 1) {
        $thresholds[$name] = $thresholds[0];
      }
      else {
        $thresholds[$name] = $thresholds[$index];
      }
    }

    $query->condition($field_conditions);
    $query->condition('d.webform_id', $webform_submission->getWebform()->id());
    $rows = $query->execute()->fetchAll();

    $precentage = 0;
    $similar_count = 0;

    foreach ($rows as $row) {

      // Calculate spaminess.
      $matching = similar_text($form_values[$row->name], $row->value, $precentage);

      // Check if spaminess is higher than config value.
      if ($matching && $precentage > $thresholds[$row->name]) {

        $similar_count++;

        if ($similar_count < $this->configuration['spamaway_anti_spam_allowed_count']) {
          continue;
        }

        $this->spamDetected($form_state, $this->t('Spam detected by similarity check on @webform due to @percentage matching post within @period', [
          '@webform' => $webform_submission->getWebform()->id(),
          '@ip' => $webform_submission->getRemoteAddr(),
          '@period' => $this->configuration['spamaway_anti_spam_ip_period'],
          '@percentage' => $precentage
        ]));

        break;
      }
    }
  }

  /**
   * Validate webform submission webform using the custom submissions table.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   */
  public function validateFormCustomSubmissions(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    ////////////////////////////////////////////////////////////////////////////
    // Check if the submitted message is similar to previous submitted messages

    $field_names = $this->getFieldNames();
    if (empty($field_names)) {
      return;
    }

    // Get the field values ...
    $query = $this->connection->select(self::SPAMAWAY_SUBMISSION_TABLE, 's');
    $query->addField('s', 'value', 'value');
    $query->addField('s', 'field_name', 'field_name');
    $query->orderBy('s.created', 'DESC');

    // for this form ...
    $query->condition('s.webform_id', $webform_submission->getWebform()->id());

    // limited to X last submissions ...
    $query->range(0, $this->configuration['spamaway_query_limit']);

    // withing a period of time ...
    $period = time() - $this->configuration['spamaway_anti_spam_period'];
    if ($this->configuration['spamaway_anti_spam_period'] && $period > 0) {
      $query->condition('s.created', $period, '>');
    }

    // limited by the fields we need ...
    $query->condition('s.field_name', $field_names, 'IN');
    $rows = $query->execute()->fetchAll();

    // Keep track of how many times we have the same field.
    $matching_count = array_combine($field_names, array_fill(0, count($field_names), 0));

    // How many times are we allowed to have the same field.
    $allowed_count = $this->explodeTrimmed(',', $this->configuration['spamaway_anti_spam_allowed_count']);
    if (count($allowed_count) == 1 || count($allowed_count)!=count($field_names)) {
      // If we have only one threshold or the numbers don't match the number
      // of fields we need take the first threshold for everything.
      $allowed_count = array_fill(0, count($field_names), $allowed_count[0]);
    }
    $allowed_count = array_combine($field_names, $allowed_count);

    foreach ($rows as $row) {

      $submitted_value = $this->getFieldValue($row->field_name, ['ip' => $webform_submission->getRemoteAddr()] + $form_state->getValues());

      if ($submitted_value === $row->value) {
        $matching_count[$row->field_name]++;

        if ($matching_count[$row->field_name] < $allowed_count[$row->field_name]) {
          continue;
        }

        $this->spamDetected($form_state, $this->t('Spam detected by hash check on @webform due to similar post within @period', [
          '@webform' => $webform_submission->getWebform()->id(),
          '@ip' => $webform_submission->getRemoteAddr(),
          '@period' => $this->configuration['spamaway_anti_spam_ip_period']
        ]));

        break;
      }
    }
  }

  protected function spamDetected(FormStateInterface $form_state, $message) {
    // We had (a) very similar submission(s) before so we ignore the new submission.
    $form_state->setErrorByName('', $this->t('Spam detected. Please contact the site administrator if the issue persists.'));
    if ($this->isLoggingEnabled()) {
      $this->getLogger('spamaway_spam')->debug($message);
    }
  }

}
