<?php

namespace Drupal\commerce_agree_terms\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Link;

/**
 * Provides the completion message pane.
 *
 * @CommerceCheckoutPane(
 *   id = "agree_terms",
 *   label = @Translation("Agree to the terms and conditions"),
 *   default_step = "review",
 * )
 */
class AgreeTerms extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'nid' => NULL,
      'link_text' => 'Terms and Conditions',
      'prefix_text' => 'I agree with the %terms',
      'invalid_text' => 'You must agree with the %terms before continuing',
      'new_window' => 1,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $prefix = $this->configuration['prefix_text'];
    $link_text = $this->configuration['link_text'];
    $invalid_text = $this->configuration['invalid_text'];
    $new_window = $this->configuration['new_window'];
    $nid = $this->configuration['nid'];
    $summary = '';
    if (!empty($prefix)) {
      $summary .= $this->t('Prefix text: @text', ['@text' => $prefix]) . '<br>';
    }
    if (!empty($link_text)) {
      $summary .= $this->t('Link text: @text', ['@text' => $link_text]) . '<br>';
    }
    if (!empty($invalid_text)) {
      $summary .= $this->t('Error text: @text', ['@text' => $invalid_text]) . '<br>';
    }
    if (isset($new_window)) {
      $window_text = ($new_window == 1) ? $this->t('New window') : $this->t('Same window');
      $summary .= $this->t('Window opens in: @text', ['@text' => $window_text]) . '<br>';
    }
    if (!empty($nid)) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if ($node) {
        $summary .= $this->t('Terms page: @title', ['@title' => $node->getTitle()]) . '<br>';
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['prefix_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix text'),
      '#default_value' => $this->configuration['prefix_text'],
      '#description' => $this->t('Use the %terms token to include the Link text to the Terms page.'),
    ];
    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $this->configuration['link_text'],
      '#required' => TRUE,
    ];
    $form['invalid_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invalid text'),
      '#default_value' => $this->configuration['invalid_text'],
    ];
    $form['new_window'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open window link in new window'),
      '#default_value' => $this->configuration['new_window'],
    ];
    if ($this->configuration['nid']) {
      $node = $this->entityTypeManager->getStorage('node')->load($this->configuration['nid']);
    }
    else {
      $node = NULL;
    }
    $form['nid'] = [
      '#title' => $this->t('Terms page'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#default_value' => $node,
      '#required' => TRUE,
      '#description' => $this->t('Select the node to point to as the Terms page.'),
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
      $this->configuration['prefix_text'] = $values['prefix_text'];
      $this->configuration['link_text'] = $values['link_text'];
      $this->configuration['invalid_text'] = $values['invalid_text'];
      $this->configuration['new_window'] = $values['new_window'];
      $this->configuration['nid'] = $values['nid'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $prefix_text = $this->configuration['prefix_text'];
    $link_text = $this->configuration['link_text'];
    $nid = $this->configuration['nid'];
    if ($nid) {
      $attributes = [];
      if ($this->configuration['new_window']) {
        $attributes = ['attributes' => ['target' => '_blank']];
      }
      $link = Link::createFromRoute($link_text, 'entity.node.canonical', ['node' => $nid], $attributes)->toString();
      $combined_text = str_replace('%terms', $link, $prefix_text);
      $pane_form['terms_and_conditions'] = [
        '#type' => 'checkbox',
        '#default_value' => FALSE,
        '#title' => !empty($this->configuration['prefix_text']) ? $combined_text : $link,
        '#required' => TRUE,
        '#weight' => $this->getWeight(),
      ];

    }
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if (!$values['terms_and_conditions']) {
      $form_state->setError($pane_form, $this->configuration['invalid_text']);
    }
  }

}
