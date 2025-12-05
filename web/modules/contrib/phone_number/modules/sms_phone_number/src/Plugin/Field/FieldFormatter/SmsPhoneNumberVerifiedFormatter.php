<?php

namespace Drupal\sms_phone_number\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\sms_phone_number\SmsPhoneNumberUtilInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'sms_phone_number_verified' formatter.
 *
 * @FieldFormatter(
 *   id = "sms_phone_number_verified",
 *   label = @Translation("Verified status"),
 *   field_types = {
 *     "sms_phone_number"
 *   }
 * )
 */
class SmsPhoneNumberVerifiedFormatter extends FormatterBase {

  /**
   * The SMS Phone Number field utility.
   *
   * @var \Drupal\sms_phone_number\SmsPhoneNumberUtilInterface
   */
  protected $smsPhoneNumberUtil;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, SmsPhoneNumberUtilInterface $sms_phone_number_util) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->smsPhoneNumberUtil = $sms_phone_number_util;
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
      $container->get('sms_phone_number.util')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      /** @var \Drupal\sms_phone_number\Plugin\Field\FieldType\SmsPhoneNumberItem $item */
      if ($this->smsPhoneNumberUtil->getPhoneNumber($item->getValue()['value'])) {
        $element[$delta] = [
          '#markup' => '<span class="verified-status' . (!empty($item->verified) ? ' verified' : '') . '">' . (!empty($item->verified) ? (string) $this->t('Verified') : (string) $this->t('Not verified')) . '</span>',
        ];
      }
    }

    return $element;
  }

}
