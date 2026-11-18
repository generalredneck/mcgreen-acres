<?php

namespace Drupal\phone_number\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\phone_number\PhoneNumberUtilInterface;
use libphonenumber\PhoneNumberFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'phone_number_international' formatter.
 *
 * @FieldFormatter(
 *   id = "phone_number_international",
 *   label = @Translation("International Number"),
 *   field_types = {
 *     "phone_number",
 *     "telephone"
 *   }
 * )
 */
class PhoneNumberInternationalFormatter extends FormatterBase {

  /**
   * The display format.
   *
   * @var int
   */
  public $phoneDisplayFormat = PhoneNumberFormat::INTERNATIONAL;

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
    return parent::defaultSettings() + ['as_link' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings() + static::defaultSettings();

    $element['as_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show as TEL link'),
      '#default_value' => $settings['as_link'],
    ];

    return parent::settingsForm($form, $form_state) + $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $settings = $this->getSettings() + static::defaultSettings();

    if (!empty($settings['as_link'])) {
      $summary[] = $this->t('Show as TEL link');
    }
    else {
      $summary[] = $this->t('Show as plaintext');
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
      if ($phone_number = $this->phoneNumberUtil->getPhoneNumber($item->getValue()['value'], NULL, $item->getValue()['extension'])) {
        if (!empty($settings['as_link'])) {
          $element[$delta] = [
            '#type' => 'link',
            '#title' => $this->phoneNumberUtil->libUtil()->format($phone_number, $this->phoneDisplayFormat),
            '#url' => Url::fromUri($this->phoneNumberUtil->getRfc3966Uri($phone_number)),
          ];
        }
        else {
          $element[$delta] = [
            '#plain_text' => $this->phoneNumberUtil->libUtil()->format($phone_number, $this->phoneDisplayFormat),
          ];
        }
      }
    }

    return $element;
  }

}
