<?php

namespace Drupal\sms_phone_number\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\phone_number\Feeds\Target\PhoneNumber;
use Drupal\phone_number\PhoneNumberUtilInterface;
use Drupal\sms_phone_number\SmsPhoneNumberUtilInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a SMS Phone Number field mapper.
 *
 * @FeedsTarget(
 *   id = "sms_phone_number",
 *   field_types = {"sms_phone_number"}
 * )
 */
class SmsPhoneNumber extends PhoneNumber implements ContainerFactoryPluginInterface {

  /**
   * The SMS Phone Number field utility.
   *
   * @var \Drupal\sms_phone_number\SmsPhoneNumberUtilInterface
   */
  protected $smsPhoneNumberUtil;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, PhoneNumberUtilInterface $phone_number_util, SmsPhoneNumberUtilInterface $sms_phone_number_util) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $phone_number_util);

    $this->smsPhoneNumberUtil = $sms_phone_number_util;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('phone_number.util'),
      $container->get('sms_phone_number.util')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return parent::prepareTarget($field_definition)
      ->addProperty('tfa')
      ->addProperty('verified');
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    // Get the basics in place.
    parent::prepareValue($delta, $values);
    // Go further if we have anything further.
    if (!empty($values['verified']) || !empty($values['tfa'])) {
      $phone_number = FALSE;
      if (!empty($values['local_number']) && !empty($values['country'])) {
        $phone_number = $this->smsPhoneNumberUtil->getPhoneNumber($values['local_number'], $values['country']);
      }
      else {
        $phone_number = $this->smsPhoneNumberUtil->getPhoneNumber($values['value']);
      }
      if ($phone_number) {
        $values['tfa'] = !empty($values['tfa']) ? 1 : 0;
        if (!empty($values['verified'])) {
          $code = $this->smsPhoneNumberUtil->generateVerificationCode();
          $token = $this->smsPhoneNumberUtil->registerVerificationCode($phone_number, $code);
          $values['verification_code'] = $code;
          $values['verification_token'] = $token;
        }
        $values['verified'] = 0;
      }
    }
  }

}
