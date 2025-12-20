<?php

namespace Drupal\mcgreen_acres_store\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provide a custom message panel in commerce checkout flow.
 *
 * @CommerceCheckoutPane(
 *   id = "newsletter_subscribe",
 *   label = @Translation("Newsletter subscribe"),
 *   display_label = @Translation("Newsletter subscribe"),
 *   default_step = "_disabled",
 *   wrapper_element = "container",
 * )
 */
class NewsletterSubscribe extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'mailchimp_list_id' => '',
      'tags' => '',
      'subscribe_text' => $this->t('Keep me updated with news and offers.'),
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $summary = $this->t('Not configured');
    if ($this->configuration['mailchimp_list_id']) {
      $list = mailchimp_get_list($this->configuration['mailchimp_list_id']);
      $mailchimp_list_label = $list->name;
      $summary = $this->t('Subscribes to the Mailchimp list: @list_id', [
        '@list_id' => $mailchimp_list_label,
      ]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['mailchimp_subscribe'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->configuration['subscribe_text'],
      '#default_value' => TRUE,
    ];
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $lists = mailchimp_get_lists();

    $options = [];
    foreach ($lists as $list) {
      $options[$list->id] = $list->name;
    }

    $form['mailchimp_list_id'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Mailchimp list'),
      '#description'   => $this->t('Select a Mailchimp list to subscribe customers to during checkout.'),
      '#options'        => $options,
      '#default_value' => $this->configuration['mailchimp_list_id'],
      '#required'      => TRUE,
    ];
    $form['tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mailchimp Audience Tags'),
      '#default_value' => $this->configuration['tags'],
      '#description' => $this->t("Type 1 or more tags separated by comma's"),
    ];
    $form['subscribe_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subscribe Text'),
      '#default_value' => $this->configuration['subscribe_text'],
      '#description' => $this->t('Text to display on the subscribe checkbox'),
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
      $this->configuration['mailchimp_list_id'] = $values['mailchimp_list_id'];
      $this->configuration['tags'] = $values['tags'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $form_values = $form_state->getValue($this->getPluginId());
    $subscribe_value = $form_values['mailchimp_subscribe'];
    $mergevars = [];
    if ($subscribe_value) {
      $email = $this->order->getEmail();
      $billing_profile = $this->order->getBillingProfile();
      if ($billing_profile) {
        /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
        $address = $billing_profile->get('address')->first();
        $first_name = $address->getGivenName();
        $last_name = $address->getFamilyName();
        $mergevars = [
          'FNAME' => $first_name,
          'LNAME' => $last_name,
        ];
      }
      if (!empty($email)) {
        $list_id = $this->configuration['mailchimp_list_id'];
        $tags = $this->configuration['tags'];
        mailchimp_subscribe(
          $list_id,
          $email,
          array_filter($mergevars),
          [],
          FALSE,
          'html',
          NULL,
          FALSE,
          $tags
        );
      }
    }
  }

}
