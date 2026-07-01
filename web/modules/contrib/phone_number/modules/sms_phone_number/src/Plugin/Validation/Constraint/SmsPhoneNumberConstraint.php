<?php

namespace Drupal\sms_phone_number\Plugin\Validation\Constraint;

use Drupal\phone_number\Plugin\Validation\Constraint\PhoneNumberConstraint;

/**
 * Validates SMS Phone Number fields.
 *
 * Includes validation for:
 *   - Number validity.
 *   - Allowed country.
 *   - Uniqueness.
 *   - Verification flood.
 *   - Phone number verification.
 *
 * @Constraint(
 *   id = "SmsPhoneNumber",
 *   label = @Translation("SMS Phone Number constraint", context = "Validation"),
 * )
 */
class SmsPhoneNumberConstraint extends PhoneNumberConstraint {

  /**
   * The flood message.
   *
   * @var string
   */
  public $flood = 'Too many verification attempts for @field_name @value, please try again in a few hours.';

  /**
   * The verification validation message.
   *
   * @var string
   */
  public $verification = 'Invalid verification code for @field_name @value.';

  /**
   * The verify required validation message.
   *
   * @var string
   */
  public $verifyRequired = 'The @field_name @value must be verified.';

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return '\Drupal\sms_phone_number\Plugin\Validation\Constraint\SmsPhoneNumberValidator';
  }

}
