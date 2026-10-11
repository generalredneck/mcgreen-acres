<?php

namespace Drupal\sms_phone_number\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\phone_number\PhoneNumberUtilInterface;
use Drupal\phone_number\Plugin\Field\FieldWidget\PhoneNumberWidget;
use Drupal\sms_phone_number\Element\SmsPhoneNumber;
use Drupal\sms_phone_number\SmsPhoneNumberUtilInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'sms_phone_number' widget.
 *
 * @FieldWidget(
 *   id = "sms_phone_number_default",
 *   label = @Translation("SMS Phone Number"),
 *   description = @Translation("SMS Phone Number field default widget."),
 *   field_types = {
 *     "sms_phone_number"
 *   }
 * )
 */
class SmsPhoneNumberWidget extends PhoneNumberWidget {

  /**
   * The SMS Phone Number field utility.
   *
   * @var \Drupal\sms_phone_number\SmsPhoneNumberUtilInterface
   */
  protected $smsPhoneNumberUtil;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, PhoneNumberUtilInterface $phone_number_util, SmsPhoneNumberUtilInterface $sms_phone_number_util) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $phone_number_util);

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
      $configuration['third_party_settings'],
      $container->get('phone_number.util'),
      $container->get('sms_phone_number.util')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $item = $items[$delta];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $items->getEntity();

    $settings = $this->getFieldSettings();
    $settings += $this->getSettings() + static::defaultSettings();

    $tfa_field = $this->smsPhoneNumberUtil->getTfaField();

    $element['#default_value']['verified'] = $item->verified;
    $element['#default_value']['tfa'] = $item->tfa;
    $element['#phone_number']['verify'] = ($this->smsPhoneNumberUtil->isSmsEnabled() && !empty($settings['verify'])) ? $settings['verify'] : SmsPhoneNumberUtilInterface::PHONE_NUMBER_VERIFY_NONE;
    $element['#phone_number']['message'] = !empty($settings['message']) ? $settings['message'] : NULL;
    $element['#phone_number']['tfa'] = (
      $entity->getEntityTypeId() == 'user' &&
      $tfa_field == $items->getFieldDefinition()->getName() &&
      $items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality() == 1
    ) ? TRUE : NULL;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state) {
    $op = SmsPhoneNumber::getOp($element, $form_state);
    $sms_phone_number = SmsPhoneNumber::getPhoneNumber($element);

    if ($op == 'sms_phone_number_send_verification' && $sms_phone_number && ($this->smsPhoneNumberUtil->checkFlood($sms_phone_number) || $this->smsPhoneNumberUtil->checkFlood($sms_phone_number, 'sms'))) {
      return FALSE;
    }

    return parent::errorElement($element, $error, $form, $form_state);
  }

}
