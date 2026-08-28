<?php

namespace Drupal\mcgreen_acres_newsletter_segments\Plugin\WebformHandler;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tags the Simplenews subscriber created by this webform's submission.
 *
 * Runs alongside the contrib "Submission Newsletter" handler, which
 * subscribes/creates the simplenews_subscriber entity but intentionally
 * skips entity reference fields like field_tags. This handler appends a
 * fixed set of taxonomy terms (from the "subscriber_tags" vocabulary,
 * auto-created if new) onto that subscriber's field_tags, so per-webform
 * tags become editable in the handler's own configuration form instead of
 * being hardcoded in code.
 *
 * @WebformHandler(
 *   id = "subscriber_tags",
 *   label = @Translation("Subscriber Tags"),
 *   category = @Translation("Newsletter"),
 *   description = @Translation("Tags the Simplenews subscriber created by this submission."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class SubscriberTagsWebformHandler extends WebformHandlerBase {

  /**
   * The subscriber tags vocabulary machine name.
   */
  const VOCABULARY = 'subscriber_tags';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The webform token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * A webform element plugin manager.
   *
   * @var \Drupal\webform\Plugin\WebformElementManagerInterface
   */
  protected $elementManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->tokenManager = $container->get('webform.token_manager');
    $instance->elementManager = $container->get('plugin.manager.webform.element');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'mail_source' => '',
      'tags' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#settings' => $this->configuration,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $element_options = [];
    $elements = $this->webform->getElementsInitializedAndFlattened();
    foreach ($elements as $key => $element) {
      $element_plugin = $this->elementManager->getElementInstance($element);
      if (!$element_plugin->isInput($element) || !isset($element['#type'])) {
        continue;
      }
      $title = isset($element['#title']) ? new FormattableMarkup('@title (@key)', [
        '@title' => $element['#title'],
        '@key' => $key,
      ]) : $key;
      $element_options[$key] = $title;
    }

    $form['mail_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Email element'),
      '#description' => $this->t('The webform element holding the address of the Simplenews subscriber to tag. This should match the "Mail" mapping used by the Submission Newsletter handler on this webform.'),
      '#options' => $element_options,
      '#required' => TRUE,
      '#default_value' => $this->configuration['mail_source'],
    ];

    $form['tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tags'),
      '#description' => $this->t('Comma-separated list of tags to add to the subscriber, e.g. "Egg Facts, Newsletter". Tags are terms in the "Subscriber tags" vocabulary and are created automatically if they do not already exist.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['tags'],
    ];

    return $this->setSettingsParentsRecursively($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE): void {
    $tag_names = array_filter(array_map('trim', explode(',', $this->configuration['tags'])));
    if (empty($tag_names) || empty($this->configuration['mail_source'])) {
      return;
    }

    $mail = $webform_submission->getElementData($this->configuration['mail_source']);
    $mail = is_string($mail) ? $this->tokenManager->replace($mail, $webform_submission) : $mail;
    if (empty($mail)) {
      return;
    }

    // The Submission Newsletter handler (which normally runs first) already
    // subscribes and saves the subscriber, so no autocreate here: if there
    // is no subscriber yet, there is nothing to tag.
    $subscriber = Subscriber::loadByMail($mail);
    if (!$subscriber || !$subscriber->hasField('field_tags')) {
      return;
    }

    $existing_ids = array_column($subscriber->get('field_tags')->getValue(), 'target_id');
    $tag_ids = array_map([$this, 'loadOrCreateTagId'], $tag_names);
    $new_ids = array_diff($tag_ids, $existing_ids);
    if (empty($new_ids)) {
      return;
    }

    foreach ($new_ids as $tid) {
      $subscriber->get('field_tags')->appendItem(['target_id' => $tid]);
    }
    $subscriber->save();
  }

  /**
   * Loads a subscriber tag term by name, creating it if it doesn't exist.
   *
   * @param string $name
   *   The term name.
   *
   * @return int
   *   The term ID.
   */
  protected function loadOrCreateTagId(string $name): int {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $storage->loadByProperties([
      'name' => $name,
      'vid' => static::VOCABULARY,
    ]);
    $term = reset($terms);

    if (!$term) {
      $term = $storage->create([
        'name' => $name,
        'vid' => static::VOCABULARY,
      ]);
      $term->save();
    }

    return (int) $term->id();
  }

}
