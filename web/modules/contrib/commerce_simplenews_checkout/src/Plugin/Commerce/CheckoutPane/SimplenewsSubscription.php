<?php

namespace Drupal\commerce_simplenews_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\simplenews\Entity\Subscriber;

/**
 * Provides the subscription information pane.
 *
 * @CommerceCheckoutPane(
 *   id = "simplenews_subscription",
 *   label = @Translation("Simplenews subscription"),
 *   default_step = "summary",
 *   wrapper_element = "container",
 * )
 */
class SimplenewsSubscription extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'newsletters' => [],
      'label' => 'Subscribe to newsletters',
      'review' => 0,
      'review_label' => 'Subscribe to newsletters: @newsletters',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $summary = '';

    if (!empty($this->configuration['newsletters'])) {
      $newsletters = $this->getNewslettersOptions();
      $summary .= $this->t('Newsletters: @newsletters', ['@newsletters' => implode(', ', $newsletters)]) . '<br/>';
    }

    if (!empty($this->configuration['label'])) {
      $summary .= $this->t('Label: @text', ['@text' => $this->configuration['label']]) . '<br/>';
    }

    if (isset($this->configuration['review'])) {
      $text = ($this->configuration['review'] == 1) ? $this->t('Yes') : $this->t('No');
      $summary .= $this->t('Display in review step: @text', ['@text' => $text]) . '<br/>';
    }

    if (!empty($this->configuration['review_label'])) {
      $summary .= $this->t('Review label: @text', ['@text' => $this->configuration['review_label']]) . '<br/>';
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form = parent::buildConfigurationForm($form, $form_state);

    $newsletters = $this->getVisibleNewsletters();
    $newsletter_options = [];

    foreach ($newsletters as $newsletter) {
      $newsletter_options[$newsletter->id] = $newsletter->name;
    }

    $form['newsletters'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Newsletters'),
      '#options' => $newsletter_options,
      '#default_value' => $this->configuration['newsletters'],
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $this->configuration['label'],
    ];
    $form['review'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display in review step'),
      '#default_value' => $this->configuration['review'],
    ];
    $form['review_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Review label'),
      '#default_value' => $this->configuration['review_label'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['newsletters'] = array_values($values['newsletters']);
      $this->configuration['label'] = $values['label'];
      $this->configuration['review'] = $values['review'];
      $this->configuration['review_label'] = $values['review_label'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {

    $pane_form = [];

    if ($this->configuration['review'] == 1) {

      $summary = $this->configuration['review_label'];
      $pane_form['subscription'] = [
        '#type' => 'markup',
        '#markup' => $summary,
      ];
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $options = $this->getNewslettersOptions();
    $pane_form['simplenews'] = [
      '#type' => 'fieldset',
      '#title' => $this->configuration['label'],
    ];
    $pane_form['simplenews']['subscriptions'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => [],
      '#required' => FALSE,
      '#suffix' => "<div class=\"description grey\">" .
      $this->t("We will write to you in your preferred language") . "</div>",
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);

    foreach ($values['simplenews']['subscriptions'] as $newsletter_id) {
      $email = $this->order->getEmail();
      $subscriber = Subscriber::loadByMail($email);
      if ($subscriber === FALSE) {
        $subscriber = Subscriber::create([
          'mail' => $email,
        ]);
        $subscriber->save();
      }
      $subscriber->subscribe($newsletter_id);
      $subscriber->save();
    }
  }

  /**
   * Get visible simplenews newsletters.
   *
   * @return object
   *   An object.
   */
  public function getVisibleNewsletters() {
    return simplenews_newsletter_get_visible();
  }

  /**
   * Get configured simplenews newsletters.
   *
   * @return object
   *   An object.
   */
  public function getConfigredNewsletters() {
    $newsletter_ids = $this->configuration['newsletters'];
    $entity_storage = \Drupal::entityTypeManager()->getStorage('simplenews_newsletter');
    $newsletters = $entity_storage->loadMultiple($newsletter_ids);
    return $newsletters;
  }

  /**
   * Get simplenews options.
   *
   * @return array
   *   An array.
   */
  public function getNewslettersOptions() {
    $newsletters = $this->getConfigredNewsletters();
    $options = [];
    foreach ($newsletters as $newsletter) {
      $options[$newsletter->id] = $newsletter->name;
    }
    return $options;
  }

}
