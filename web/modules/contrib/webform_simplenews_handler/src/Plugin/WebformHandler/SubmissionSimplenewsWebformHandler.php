<?php

namespace Drupal\webform_simplenews_handler\Plugin\WebformHandler;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simplenews\SubscriberInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Utility\WebformOptionsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Save a webform submission's with automatic subscription to newsletter.
 *
 * @WebformHandler(
 *   id = "submission_newsletter",
 *   label = @Translation("Submission Newsletter"),
 *   category = @Translation("Newsletter"),
 *   description = @Translation("Sends a webform submission into newsletter."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class SubmissionSimplenewsWebformHandler extends WebformHandlerBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The webform token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * The webform theme manager.
   *
   * @var \Drupal\webform\WebformThemeManagerInterface
   */
  protected $themeManager;

  /**
   * A webform element plugin manager.
   *
   * @var \Drupal\webform\Plugin\WebformElementManagerInterface
   */
  protected $elementManager;

  /**
   * Cache of default configuration values.
   *
   * @var array
   */
  protected $defaultValues;

  /**
   * Subscription management: subscribe, unsubscribe and get status.
   *
   * @var \Drupal\simplenews\Subscription\SubscriptionManagerInterface
   */
  protected $subscriptionManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Subscriber entity field types eligible for webform value mapping.
   *
   * Kept to simple text-like field types: complex targets (entity
   * references such as field_tags, computed/base management fields like
   * status or uid) are intentionally out of scope for a raw value mapping.
   */
  const MAPPABLE_FIELD_TYPES = ['string', 'string_long', 'email', 'telephone'];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $instance->loggerFactory = $container->get('logger.factory');
    $instance->configFactory = $container->get('config.factory');
    $instance->conditionsValidator = $container->get('webform_submission.conditions_validator');
    $instance->currentUser = $container->get('current_user');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->languageManager = $container->get('language_manager');
    $instance->themeManager = $container->get('webform.theme_manager');
    $instance->tokenManager = $container->get('webform.token_manager');
    $instance->elementManager = $container->get('plugin.manager.webform.element');
    $instance->subscriptionManager = $container->get('simplenews.subscription_manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'states' => [WebformSubmissionInterface::STATE_COMPLETED],
      'newsletters_lst' => [],
      // Deprecated: superseded by field_mapping_source['mail']. Kept so
      // existing exported webform config continues to work unmodified.
      'token' => 'default',
      'field_mapping_source' => [],
      'field_mapping_value' => [],
      'action' => '',
      'debug' => FALSE,
    ];
  }

  /**
   * Get configuration default values.
   *
   * @return array
   *   Configuration default values.
   */
  protected function getDefaultConfigurationValues() {
    if (isset($this->defaultValues)) {
      return $this->defaultValues;
    }

    $webform_settings = $this->configFactory->get('webform.settings');

    $this->defaultValues = [
      'states' => [WebformSubmissionInterface::STATE_COMPLETED],
      'token' => $webform_settings->get('submission_newsletter.token') ?: '',
      'newsletters_lst' => $webform_settings->get('submission_newsletter.newsletters_lst') ?: '',
    ];

    return $this->defaultValues;
  }

  /**
   * Get configuration default value.
   *
   * @param string $name
   *   Configuration name.
   *
   * @return string|array
   *   Configuration default value.
   */
  protected function getDefaultConfigurationValue($name) {
    $default_values = $this->getDefaultConfigurationValues();
    return $default_values[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {

    $settings = $this->getSubmissionNewsletterConfiguration();
    // Simplify the [webform_submission:values:.*] tokens.
    array_walk($settings, function (&$value, $key) {
      if (is_string($value)) {
        $value = preg_replace('/\[webform:([^:]+)\]/', '[\1]', $value);
        $value = preg_replace('/\[webform_role:([^:]+)\]/', '[\1]', $value);
        $value = preg_replace('/\[webform_submission:(?:node|source_entity|values):([^]]+)\]/', '[\1]', $value);
        $value = preg_replace('/\[webform_submission:([^]]+)\]/', '[\1]', $value);
        $value = preg_replace('/(:raw|:value)(:html)?\]/', ']', $value);
      }
    });

    $newsletter_options = $this->getNewsletterOptions();

    $settings['newsletters_lst'] = array_intersect_key($newsletter_options, array_combine($settings['newsletters_lst'], $settings['newsletters_lst']));

    $states = [
      WebformSubmissionInterface::STATE_DRAFT => $this->t('Draft Saved'),
      WebformSubmissionInterface::STATE_CONVERTED => $this->t('Converted'),
      WebformSubmissionInterface::STATE_COMPLETED => $this->t('Completed'),
      WebformSubmissionInterface::STATE_UPDATED => $this->t('Updated'),
    ];
    $settings['states'] = array_intersect_key($states, array_combine($settings['states'], $settings['states']));

    $field_labels = $this->getSubscriberFieldOptions();
    $field_mapping = [];
    foreach ($this->getFieldMapping() as $field_name => $source) {
      $label = $field_labels[$field_name] ?? $field_name;
      $field_mapping[(string) $label] = $source;
    }
    $settings['field_mapping'] = $field_mapping;
    unset($settings['field_mapping_source'], $settings['field_mapping_value'], $settings['token']);

    return [
      '#settings' => $settings,
    ] + parent::getSummary();
  }

  /**
   * Get simplenews_subscriber fields eligible for webform value mapping.
   *
   * @return array
   *   An associative array of field labels, keyed by field name.
   */
  protected function getSubscriberFieldOptions() {
    $options = [];
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('simplenews_subscriber', 'simplenews_subscriber');
    foreach ($field_definitions as $field_name => $field_definition) {
      if (in_array($field_definition->getType(), static::MAPPABLE_FIELD_TYPES, TRUE)) {
        $options[$field_name] = $field_definition->getLabel();
      }
    }
    return $options;
  }

  /**
   * Get the resolved field mapping.
   *
   * Collapses the source/value form storage into a single map of subscriber
   * field name to a webform element key or a literal/token string, falling
   * back to the legacy 'token' setting for the mail field.
   *
   * @return array
   *   An associative array of webform element keys or literal/token strings,
   *   keyed by subscriber field name.
   */
  protected function getFieldMapping() {
    $sources = $this->configuration['field_mapping_source'] ?? [];
    $custom_values = $this->configuration['field_mapping_value'] ?? [];

    if (empty($sources['mail'])) {
      $legacy_token = $this->configuration['token'] ?? '';
      if ($legacy_token === 'default') {
        $legacy_token = $this->getDefaultConfigurationValue('token');
      }
      if ($legacy_token !== '') {
        $sources['mail'] = $legacy_token;
      }
    }

    $mapping = [];
    foreach ($sources as $field_name => $source) {
      if ($source === '' || $source === NULL) {
        continue;
      }
      if ($source === '_other') {
        if (isset($custom_values[$field_name]) && $custom_values[$field_name] !== '') {
          $mapping[$field_name] = $custom_values[$field_name];
        }
        continue;
      }
      $mapping[$field_name] = $source;
    }

    return $mapping;
  }

  /**
   * Resolve a field mapping source to its submitted value.
   *
   * @param string $source
   *   A webform element key, or a literal string that may contain tokens.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   *
   * @return string|null
   *   The resolved value.
   */
  protected function resolveFieldMappingValue($source, WebformSubmissionInterface $webform_submission) {
    $elements = $this->webform->getElementsInitializedAndFlattened();
    if (isset($elements[$source])) {
      return $webform_submission->getElementData($source);
    }
    return $this->tokenManager->replace($source, $webform_submission);
  }

  /**
   * Get mail configuration values.
   *
   * @return array
   *   An associative array containing email configuration values.
   */
  protected function getSubmissionNewsletterConfiguration() {
    $configuration = $this->getConfiguration();
    $submission_newsletter = [];
    foreach ($configuration['settings'] as $key => $value) {
      $submission_newsletter[$key] = ($value === 'default') ? $this->getDefaultConfigurationValue($key) : $value;
    }
    return $submission_newsletter;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    parent::applyFormStateToConfiguration($form_state);

    // Cleanup states.
    $this->configuration['states'] = array_values(array_filter($this->configuration['states']));
    $this->configuration['newsletters_lst'] = array_values(array_filter($this->configuration['newsletters_lst']));

    $values = $form_state->getValues();

    foreach ($this->configuration as $name => $value) {
      if (isset($values[$name])) {
        // Convert options array to safe config array to prevent errors.
        // @see https://www.drupal.org/node/2297311
        if (preg_match('/_options$/', $name)) {
          $this->configuration[$name] = WebformOptionsHelper::encodeConfig($values[$name]);
        }
        else {
          $this->configuration[$name] = $values[$name];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMessageSummary(array $message) {
    return [
      '#settings' => $message,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $values = $form_state->getValues();

    if (($values['field_mapping_source']['mail'] ?? '') === '_other' && trim($values['field_mapping_value']['mail'] ?? '') === '') {
      $form_state->setError($form['submission_newsletter']['field_mapping']['mail']['custom'], $this->t('A custom value or token is required when Mail is mapped to "Other".'));
    }

    $form_state->setValues($values);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $results_disabled = $this->getWebform()->getSetting('results_disabled');

    $form['trigger'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Trigger'),
    ];
    $form['trigger']['states'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Execute'),
      '#options' => [
        WebformSubmissionInterface::STATE_DRAFT => $this->t('...when <b>draft</b> is saved.'),
        WebformSubmissionInterface::STATE_CONVERTED => $this->t('...when anonymous submission is <b>converted</b> to authenticated.'),
        WebformSubmissionInterface::STATE_COMPLETED => $this->t('...when submission is <b>completed</b>.'),
        WebformSubmissionInterface::STATE_UPDATED => $this->t('...when submission is <b>updated</b>.'),
      ],
      '#required' => TRUE,
      '#access' => $results_disabled ? FALSE : TRUE,
      '#default_value' => $results_disabled ? [WebformSubmissionInterface::STATE_COMPLETED] : $this->configuration['states'],
    ];

    // Get options, mail, and text elements as options (text/value).
    $element_options_value = [];

    $elements = $this->webform->getElementsInitializedAndFlattened();
    foreach ($elements as $key => $element) {
      $element_plugin = $this->elementManager->getElementInstance($element);
      if (!$element_plugin->isInput($element) || !isset($element['#type'])) {
        continue;
      }

      $title = (isset($element['#title'])) ? new FormattableMarkup('@title (@key)', [
        '@title' => $element['#title'],
        '@key' => $key,
      ]) : $key;
      $element_options_value[$key] = $title;
    }

    $newsletter_options = $this->getNewsletterOptions();

    $form['submission_newsletter'] = [
      '#type' => 'details',
      '#title' => $this->t('Newsletter'),
      '#open' => TRUE,
    ];

    // Map webform element values (or a custom literal/token string) onto
    // simplenews_subscriber fields. The 'mail' row is required: it replaces
    // the legacy single 'token' select, whose value is still honored as a
    // fallback default for 'mail' when no mapping has been saved yet.
    $mapping_sources = $this->configuration['field_mapping_source'];
    $mapping_values = $this->configuration['field_mapping_value'];
    if (empty($mapping_sources['mail'])) {
      $legacy_token = ($this->configuration['token'] === 'default')
        ? $this->getDefaultConfigurationValue('token')
        : $this->configuration['token'];
      if ($legacy_token !== '') {
        $mapping_sources['mail'] = $legacy_token;
      }
    }

    $form['submission_newsletter']['field_mapping'] = [
      '#type' => 'container',
    ];

    foreach ($this->getSubscriberFieldOptions() as $field_name => $field_label) {
      $default_source = $mapping_sources[$field_name] ?? '';

      $form['submission_newsletter']['field_mapping'][$field_name] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['webform-simplenews-handler-field-mapping-row']],
      ];
      $form['submission_newsletter']['field_mapping'][$field_name]['source'] = [
        '#type' => 'select',
        '#title' => $field_label,
        '#options' => [
          '' => $this->t('- None -'),
          (string) $this->t('Elements') => $element_options_value,
          '_other' => $this->t('- Other (custom value or token) -'),
        ],
        '#required' => ($field_name === 'mail'),
        '#default_value' => $default_source,
        '#parents' => ['settings', 'field_mapping_source', $field_name],
      ];
      $form['submission_newsletter']['field_mapping'][$field_name]['custom'] = [
        '#type' => 'textfield',
        '#title' => $this->t('@field custom value or token', ['@field' => $field_label]),
        '#title_display' => 'invisible',
        '#placeholder' => $this->t('Custom value or token'),
        '#default_value' => $mapping_values[$field_name] ?? '',
        '#parents' => ['settings', 'field_mapping_value', $field_name],
        '#states' => [
          'visible' => [
            ':input[name="settings[field_mapping_source][' . $field_name . ']"]' => ['value' => '_other'],
          ],
        ],
      ];
    }

    $form['submission_newsletter']['newsletters_lst'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Newsletters'),
      '#options' => $newsletter_options,
      '#default_value' => $this->configuration['newsletters_lst'],
    ];

    $form['submission_newsletter']['action'] = [
      '#type' => 'select',
      '#title' => $this->t('Subscribe or Unsubscribe'),
      '#options' => [
        'subscribe' => $this->t('Subscribe'),
        'unsubscribe' => $this->t('Unsubscribe'),
      ],
      '#default_value' => $this->configuration['action'],
    ];

    return $this->setSettingsParentsRecursively($form);
  }

  /**
   * Get newsletter options values.
   *
   * @return array
   *   An associative array containing newsletter options
   */
  public function getNewsletterOptions() {
    $newsletter_options = [];
    // Users with the "manage simplenews hidden subscriptions" permission may
    // also subscribe/unsubscribe submitters to newsletters with a "hidden"
    // access setting, not just publicly visible ones.
    // @see https://www.drupal.org/project/simplenews/issues/2111981
    $newsletters = $this->currentUser->hasPermission('manage simplenews hidden subscriptions')
      ? simplenews_newsletter_get_all()
      : simplenews_newsletter_get_visible();

    if (!empty($newsletters)) {
      foreach ($newsletters as $key => $newsletter) {
        $label = $newsletter->name;
        if (!$newsletter->isAccessible()) {
          $label = $this->t('@label (hidden)', ['@label' => $label]);
        }
        $newsletter_options[$key] = $label;
      }
    }

    return $newsletter_options;
  }

  /**
   * {@inheritdoc}
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE): void {
    $state = $webform_submission->getWebform()->getSetting('results_disabled') ? WebformSubmissionInterface::STATE_COMPLETED : $webform_submission->getState();

    if (!in_array($state, $this->configuration['states'])) {
      return;
    }

    $newsletter_lst = $this->configuration['newsletters_lst'];
    $field_mapping = $this->getFieldMapping();

    if (empty($field_mapping['mail']) || empty($newsletter_lst)) {
      return;
    }

    $email_value = $this->resolveFieldMappingValue($field_mapping['mail'], $webform_submission);
    if (!$email_value) {
      return;
    }

    $action = $this->configuration['action'];
    foreach ($newsletter_lst as $newsletter_id) {
      if ($newsletter_id != '0') {
        $this->subscriptionManager->$action($email_value, $newsletter_id, NULL);
      }
    }

    // Get subscriber entity.
    $subscriber = Subscriber::loadByMail($email_value);
    if (!$subscriber::skipConfirmation()) {
      // Set new (anonymous) subscribers to unconfirmed.
      $subscriber->setStatus(SubscriberInterface::UNCONFIRMED);
    }

    foreach ($field_mapping as $field_name => $source) {
      if ($field_name === 'mail' || !$subscriber->hasField($field_name)) {
        continue;
      }
      $value = $this->resolveFieldMappingValue($source, $webform_submission);
      if ($value !== NULL && $value !== '') {
        $subscriber->set($field_name, $value);
      }
    }

    $subscriber->sendConfirmation();
    $subscriber->save();
  }

}
