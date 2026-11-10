<?php

namespace Drupal\phone_number\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\phone_number\PhoneNumberUtilInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'phone_number_country' formatter.
 *
 * @FieldFormatter(
 *   id = "phone_number_country",
 *   label = @Translation("Country"),
 *   field_types = {
 *     "phone_number"
 *   }
 * )
 */
class PhoneNumberCountryFormatter extends FormatterBase {

  /**
   * The Phone Number field utility.
   *
   * @var \Drupal\phone_number\PhoneNumberUtilInterface
   */
  protected $phoneNumberUtil;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, PhoneNumberUtilInterface $phone_number_util) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->phoneNumberUtil = $phone_number_util;
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
      $container->get('phone_number.util')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings() + ['type' => 'name'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $settings = $this->getSettings() + static::defaultSettings();

    $form['type'] = [
      '#type' => 'radios',
      '#options' => [
        'name' => $this->t('Country name'),
        'code' => $this->t('Country code'),
      ],
      '#default_value' => $settings['type'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $settings = $this->getSettings() + static::defaultSettings();

    if (!empty($settings['type'])) {
      $texts = [
        'name' => $this->t('Show as country name'),
        'code' => $this->t('Show as country code'),
      ];
      $summary[] = $texts[$settings['type']];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $settings = $this->getSettings() + static::defaultSettings();

    foreach ($items as $delta => $item) {
      /** @var \Drupal\phone_number\Plugin\Field\FieldType\PhoneNumberItem $item */
      if ($phone_number = $this->phoneNumberUtil->getPhoneNumber($item->getValue()['value'])) {
        if ($settings['type'] == 'code') {
          $element[$delta] = [
            '#plain_text' => $this->phoneNumberUtil->getCountry($phone_number),
          ];
        }
        else {
          $element[$delta] = [
            '#plain_text' => $this->phoneNumberUtil->getCountryName($this->phoneNumberUtil->getCountry($phone_number)),
          ];
        }
      }
    }

    return $element;
  }

}
