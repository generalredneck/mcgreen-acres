<?php

namespace Drupal\sms_phone_number\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\phone_number\Exception\PhoneNumberException;
use Drupal\sms_phone_number\SmsPhoneNumberUtilInterface;
use libphonenumber\PhoneNumberFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates a SMS Phone Number.
 *
 * Validates:
 *   - Number validity.
 *   - Allowed country.
 *   - Uniqueness.
 *   - Verification flood.
 *   - Phone number verification.
 */
class SmsPhoneNumberValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The Phone Number field utility.
   *
   * @var \Drupal\phone_number\PhoneNumberUtilInterface
   */
  protected $smsPhoneNumberUtil;

  /**
   * Constructs a new SmsPhoneNumberValidator.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\sms_phone_number\SmsPhoneNumberUtilInterface $sms_phone_number_util
   *   The SMS Phone Number field utility.
   */
  public function __construct(AccountProxyInterface $current_user, SmsPhoneNumberUtilInterface $sms_phone_number_util) {
    $this->currentUser = $current_user;
    $this->smsPhoneNumberUtil = $sms_phone_number_util;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('sms_phone_number.util')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($item, Constraint $constraint) {
    /** @var \Drupal\sms_phone_number\Plugin\Field\FieldType\SmsPhoneNumberItem $item */
    $values = $item->getValue();
    if ((empty($values['value']) && empty($values['local_number']))) {
      return;
    }

    $field_label = $item->getFieldDefinition()->getLabel();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $item->getEntity();
    $entity_type = $entity->getEntityType()->getSingularLabel();
    $allowed_countries = $item->getFieldDefinition()->getSetting('allowed_countries');
    $verify = $item->getFieldDefinition()->getSetting('verify');
    $unique = $item->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getSetting('unique');
    $tfa = $item->get('tfa')->getValue();

    try {
      $phone_number = $item->getPhoneNumber(TRUE);
      $country = $this->smsPhoneNumberUtil->getCountry($phone_number);
      $display_number = $this->smsPhoneNumberUtil->libUtil()->format($phone_number, PhoneNumberFormat::NATIONAL);
      if ($allowed_countries && !in_array($this->smsPhoneNumberUtil->getCountry($phone_number), $allowed_countries)) {
        $this->context->addViolation($constraint->allowedCountry, [
          '@value' => $this->smsPhoneNumberUtil->getCountryName($country),
          '@field_name' => mb_strtolower($field_label),
        ]);
      }
      else {
        $bypass_verification = $this->currentUser->hasPermission('bypass phone number verification requirement');
        $verification = $item->verify();

        if ($verification === -1) {
          $this->context->addViolation($constraint->flood, [
            '@value' => $display_number,
            '@field_name' => mb_strtolower($field_label),
          ]);
        }
        elseif ($verification === FALSE) {
          $this->context->addViolation($constraint->verification, [
            '@value' => $display_number,
            '@field_name' => mb_strtolower($field_label),
          ]);
        }
        elseif (!$verification && !$bypass_verification && ($tfa || $verify === SmsPhoneNumberUtilInterface::PHONE_NUMBER_VERIFY_REQUIRED)) {
          $this->context->addViolation($constraint->verifyRequired, [
            '@value' => $display_number,
            '@entity_type' => $entity_type,
            '@field_name' => mb_strtolower($field_label),
          ]);
        }
        elseif ($unique && !$item->isUnique($unique)) {
          $this->context->addViolation($constraint->unique, [
            '@value' => $display_number,
            '@entity_type' => $entity_type,
            '@field_name' => mb_strtolower($field_label),
          ]);
        }
      }
    }
    catch (PhoneNumberException $e) {
      $this->context->addViolation($constraint->validity, [
        '@value' => $values['local_number'],
        '@entity_type' => $entity_type,
        '@field_name' => mb_strtolower($field_label),
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
