<?php

namespace Drupal\phone_number\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\ConfigurableTargetInterface;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;
use Drupal\phone_number\PhoneNumberUtilInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Phone Number field mapper.
 *
 * @FeedsTarget(
 *   id = "phone_number",
 *   field_types = {"phone_number"}
 * )
 */
class PhoneNumber extends FieldTargetBase implements ConfigurableTargetInterface, ContainerFactoryPluginInterface {

  /**
   * The Phone Number field utility.
   *
   * @var \Drupal\phone_number\PhoneNumberUtilInterface
   */
  protected $phoneNumberUtil;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, PhoneNumberUtilInterface $phone_number_util) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->phoneNumberUtil = $phone_number_util;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('phone_number.util')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('value')
      ->addProperty('local_number')
      ->addProperty('country')
      ->addProperty('extension');
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    $phone_number = FALSE;
    $extension = !empty($values['extension']) ? $values['extension'] : NULL;
    if (!empty($values['local_number']) && !empty($values['country'])) {
      $phone_number = $this->phoneNumberUtil->getPhoneNumber($values['local_number'], $values['country'], $extension);
    }
    else {
      $phone_number = $this->phoneNumberUtil->getPhoneNumber($values['value'], NULL, $extension);
    }
    if ($phone_number) {
      $values['value'] = $this->phoneNumberUtil->getCallableNumber($phone_number);
      $values['local_number'] = $this->phoneNumberUtil->getLocalNumber($phone_number, TRUE);
      $values['country'] = $this->phoneNumberUtil->getCountry($phone_number);
      $values['extension'] = $phone_number->getExtension();
    }
    else {
      $values = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return '';
  }

}
