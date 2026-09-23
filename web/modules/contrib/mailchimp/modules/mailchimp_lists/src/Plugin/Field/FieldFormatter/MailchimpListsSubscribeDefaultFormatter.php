<?php

namespace Drupal\mailchimp_lists\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mailchimp\ApiService;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'mailchimp_lists_subscribe_default' formatter.
 *
 * @FieldFormatter (
 *   id = "mailchimp_lists_subscribe_default",
 *   label = @Translation("Subscription Info"),
 *   field_types = {
 *     "mailchimp_lists_subscription"
 *   }
 * )
 */
class MailchimpListsSubscribeDefaultFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The Mailchimp API service.
   *
   * @var \Drupal\mailchimp\ApiService
   */
  protected $apiService;

  /**
   * Constructs a default formatter.
   *
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field the formatter is associated with.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\mailchimp\ApiService $apiService
   *   The Mailchimp API service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ApiService $apiService) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->apiService = $apiService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('mailchimp.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = [
      'show_interest_groups' => FALSE,
    ];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $field_settings = $this->getFieldSettings();
    $settings = $this->getSettings();

    $form['show_interest_groups'] = [
      '#title' => $this->t('Show Interest Groups'),
      '#type' => 'checkbox',
      '#description' => $field_settings['show_interest_groups'] ? $this->t('Check to display interest group membership details.') : $this->t('To display Interest Groups, first enable them in the field instance settings.'),
      '#default_value' => $field_settings['show_interest_groups'] && $settings['show_interest_groups'],
      '#disabled' => !$field_settings['show_interest_groups'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $field_settings = $this->getFieldSettings();
    $settings = $this->getSettings();

    $summary = [];

    if ($field_settings['show_interest_groups'] && $settings['show_interest_groups']) {
      $summary[] = $this->t('Display Interest Groups');
    }
    else {
      $summary[] = $this->t('Hide Interest Groups');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    /** @var \Drupal\mailchimp_lists\Plugin\Field\FieldType\MailchimpListsSubscription $item */
    foreach ($items as $delta => $item) {
      $elements[$delta] = [];

      $field_settings = $this->getFieldSettings();

      $mc_list = mailchimp_get_list($field_settings['mc_list_id']);
      $email = mailchimp_lists_load_email($item, $item->getEntity(), FALSE);

      if ($email && !empty($mc_list)) {
        if (mailchimp_is_subscribed($field_settings['mc_list_id'], $email)) {
          $status = $this->t('Subscribed to %list', ['%list' => $mc_list->name]);
        }
        else {
          $status = $this->t('Not subscribed to %list', ['%list' => $mc_list->name]);
        }
      }
      else {
        $status = $this->t('Invalid email configuration.');
      }
      $elements[$delta]['status'] = [
        '#markup' => $status,
        '#description' => $this->t('@mc_list_description', [
          '@mc_list_description' => $item->getFieldDefinition()
            ->getDescription(),
        ]),
      ];

      if ($field_settings['show_interest_groups'] && $this->getSetting('show_interest_groups')) {
        $member_info = $this->apiService->getMemberInfo($field_settings['mc_list_id'], $email);

        if (!empty($mc_list->intgroups)) {
          $elements[$delta]['interest_groups'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Interest Groups'),
            '#weight' => 100,
          ];

          foreach ($mc_list->intgroups as $interest_group) {
            $items = [];
            foreach ($interest_group->interests as $interest) {
              if (isset($member_info->interests->{$interest->id}) && ($member_info->interests->{$interest->id} === TRUE)) {
                $items[] = $interest->name;
              }
            }

            if (count($items) > 0) {
              $elements[$delta]['interest_groups'][$interest_group->id] = [
                '#title' => $interest_group->title,
                '#theme' => 'item_list',
                '#items' => $items,
                '#type' => 'ul',
              ];
            }
          }
        }

      }
    }

    return $elements;
  }

}
