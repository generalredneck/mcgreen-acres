<?php

namespace Drupal\phone_number\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\phone_number\Exception\PhoneNumberException;
use Drupal\phone_number\PhoneNumberUtilInterface;
use libphonenumber\PhoneNumberFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates a phone number.
 *
 * Validates:
 *   - Number validity.
 *   - Allowed country.
 *   - Uniqueness.
 */
class PhoneNumberValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The Phone Number field utility.
   *
   * @var \Drupal\phone_number\PhoneNumberUtilInterface
   */
  protected $phoneNumberUtil;

  /**
   * Constructs a new PhoneNumberValidator.
   *
   * @param \Drupal\phone_number\PhoneNumberUtilInterface $phone_number_util
   *   The Phone Number field utility.
   */
  public function __construct(PhoneNumberUtilInterface $phone_number_util) {
    $this->phoneNumberUtil = $phone_number_util;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('phone_number.util')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($item, Constraint $constraint) {
    /** @var \Drupal\phone_number\Plugin\Field\FieldType\PhoneNumberItem $item */
    $values = $item->getValue();
    if ((empty($values['value']) && empty($values['local_number']))) {
      return;
    }

    $field_label = $item->getFieldDefinition()->getLabel();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $item->getEntity();
    $entity_type = $entity->getEntityType()->getSingularLabel();
    $allowed_countries = $item->getFieldDefinition()->getSetting('allowed_countries');
    $unique = $item->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getSetting('unique');

    try {
      $phone_number = $item->getPhoneNumber(TRUE);
      $country = $this->phoneNumberUtil->getCountry($phone_number);
      $display_number = $this->phoneNumberUtil->libUtil()->format($phone_number, PhoneNumberFormat::NATIONAL);
      if (!in_array($this->phoneNumberUtil->getCountry($phone_number), $allowed_countries) && $allowed_countries) {
        $this->context->addViolation($constraint->allowedCountry, [
          '@value' => $this->phoneNumberUtil->getCountryName($country),
          '@field_name' => mb_strtolower($field_label),
        ]);
      }
      elseif ($unique && !$item->isUnique()) {
        $this->context->addViolation($constraint->unique, [
          '@value' => $display_number,
          '@entity_type' => $entity_type,
          '@field_name' => mb_strtolower($field_label),
        ]);
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
