<?php

namespace Drupal\mailchimp_lists\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\mailchimp\ApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure settings for a Mailchimp audience webhook.
 */
class MailchimpListsWebhookSettingsForm extends ConfigFormBase {

  /**
   * The Mailchimp API service.
   *
   * @var \Drupal\mailchimp\ApiService
   */
  protected $apiService;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->apiService = $container->get('mailchimp.api');
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mailchimp_lists_webhook_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['mailchimp_lists.webhook'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $list_id = $this->getRequest()->attributes->get('_raw_variables')->get('list_id');

    $list = mailchimp_get_list($list_id);

    $form_state->set('list', $list);

    $default_webhook_events = mailchimp_lists_default_webhook_events();
    $enabled_webhook_events = mailchimp_lists_enabled_webhook_events($list_id);

    $form['webhook_events'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enabled webhook events for the @name audience',
        [
          '@name' => $list->name,
        ]),
      '#tree' => TRUE,
    ];

    foreach ($default_webhook_events as $event => $name) {
      $form['webhook_events'][$event] = [
        '#type' => 'checkbox',
        '#title' => $name,
        '#default_value' => in_array($event, $enabled_webhook_events),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $list = $form_state->get('list');

    $webhook_events = $form_state->getValue('webhook_events');

    $events = [];
    foreach ($webhook_events as $webhook_id => $enable) {
      $events[$webhook_id] = ($enable === 1);
    }

    $result = FALSE;

    if (count($events) > 0) {
      $webhook_url = mailchimp_webhook_url();

      $webhooks = $this->apiService->webhookGet($list->id);

      if (!empty($webhooks)) {
        foreach ($webhooks as $webhook) {
          if ($webhook->url == $webhook_url) {
            // Delete current webhook.
            $this->apiService->webhookDelete($list->id, mailchimp_webhook_url());
          }
        }
      }

      $sources = [
        'user' => TRUE,
        'admin' => TRUE,
        'api' => FALSE,
      ];

      // Add webhook with enabled events.
      $result = $this->apiService->webhookAdd(
        $list->id,
        mailchimp_webhook_url(),
        $events,
        $sources
      );
    }

    if ($result) {
      $this->messenger->addStatus($this->t('Webhooks for audience "%name" have been updated.',
        [
          '%name' => $list->name,
        ]
      ));
    }
    else {
      $this->messenger->addWarning($this->t('Unable to update webhooks for audience "%name".',
        [
          '%name' => $list->name,
        ]
      ));
    }

    $form_state->setRedirect('mailchimp_lists.overview');
  }

}
