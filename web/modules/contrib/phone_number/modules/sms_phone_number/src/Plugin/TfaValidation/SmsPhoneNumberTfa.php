<?php

namespace Drupal\sms_phone_number\Plugin\TfaValidation;

/**
 * @file
 * SmsPhoneNumberTfa.php
 */

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\phone_number\Exception\PhoneNumberException;
use Drupal\sms_phone_number\SmsPhoneNumberUtilInterface;
use Drupal\tfa\Plugin\TfaBasePlugin;
use Drupal\tfa\Plugin\TfaSendInterface;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\user\UserDataInterface;

/**
 * Class SmsPhoneNumberTfa is a validation and sending plugin for TFA.
 *
 * @package Drupal\sms_phone_number
 *
 * @ingroup sms_phone_number
 */
class SmsPhoneNumberTfa extends TfaBasePlugin implements TfaValidationInterface, TfaSendInterface {

  /**
   * The SMS Phone Number field utility.
   *
   * @var \Drupal\sms_phone_number\SmsPhoneNumberUtilInterface
   */
  public $smsPhoneNumberUtil;

  /**
   * Libphonenumber phone number object.
   *
   * @var \libphonenumber\PhoneNumber
   */
  public $phoneNumber;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manger interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The SMS Phone Number config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $smsPhoneNumberConfig;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserDataInterface $user_data, EncryptionProfileManagerInterface $encryption_profile_manager, EncryptServiceInterface $encrypt_service, SmsPhoneNumberUtilInterface $smsPhoneNumberUtil, MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $user_data, $encryption_profile_manager, $encrypt_service);
    $this->smsPhoneNumberUtil = $smsPhoneNumberUtil;
    $this->messenger = $messenger;
    $this->logger = \Drupal::logger('sms_phone_number_tfa');
    $this->entityTypeManager = $entity_type_manager;
    $this->smsPhoneNumberConfig = $config_factory->get('sms_phone_number.settings');

    // @todo It would appear $context is not set at this point.  This section
    // needs work.
    if (!empty($context['validate_context']) && !empty($context['validate_context']['code'])) {
      $this->code = $context['validate_context']['code'];
    }

    if (!empty($context['validate_context']) && !empty($context['validate_context']['verification_token'])) {
      $this->verificationToken = $context['validate_context']['verification_token'];
    }

    $this->codeLength = 4;

    if ($m = $this->smsPhoneNumberUtil->tfaAccountNumber($context['uid'])) {
      try {
        $this->phoneNumber = $this->smsPhoneNumberUtil->testPhoneNumber($m);
      }
      catch (PhoneNumberException $e) {
        throw new Exception("Two factor authentication failed: \n" . $e->getMessage(), $e->getCode(), $e);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function ready() {
    return $this->smsPhoneNumberUtil->tfaAccountNumber(($this->context['uid'])) ? TRUE : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function begin() {
    if (!$this->code) {
      if (!$this->sendCode()) {
        $this->messenger->addError($this->t('Unable to deliver the code. Please contact support.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form, FormStateInterface $form_state) {
    $local_number = $this->smsPhoneNumberUtil->getLocalNumber($this->phoneNumber, TRUE);
    $numberClue = str_pad(substr($local_number, -3, 3), strlen($local_number), 'X', STR_PAD_LEFT);
    $numberClue = substr_replace($numberClue, '-', 3, 0);

    $form['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification Code'),
      '#required' => TRUE,
      '#description' => $this->t('A verification code was sent to %clue. Enter the @length-character code sent to your device.', [
        '@length' => $this->codeLength,
        '%clue' => $numberClue,
      ]),
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['login'] = [
      '#type' => 'submit',
      '#value' => $this->t('Verify'),
    ];

    $form['actions']['resend'] = [
      '#type' => 'submit',
      '#value' => $this->t('Resend'),
      '#submit' => ['tfa_form_submit'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state) {
    // If operation is resend then do not attempt to validate code.
    if ($form_state['values']['op'] === $form_state['values']['resend']) {
      return TRUE;
    }
    elseif (!$this->verifyCode($form_state['values']['code'])) {
      $this->errorMessages['code'] = $this->t('Invalid code.');
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface &$form_state) {
    // Resend code if pushed.
    if ($form_state['values']['op'] === $form_state['values']['resend']) {
      if (!$this->smsPhoneNumberUtil->checkFlood($this->phoneNumber, 'sms')) {
        $this->messenger->addError($this->t('Too many verification code requests, please try again shortly.'));
      }
      elseif (!$this->sendCode()) {
        $this->messenger->addError($this->t('Unable to deliver the code. Please contact support.'), 'error');
      }
      else {
        $this->messenger->addMessage($this->t('Code resent'));
      }

      return FALSE;
    }
    else {
      return parent::submitForm($form, $form_state);
    }
  }

  /**
   * Return context for this plugin.
   */
  public function getPluginContext() {
    return [
      'code' => $this->code,
      'verification_token' => !empty($this->verificationToken) ? $this->verificationToken : '',
    ];
  }

  /**
   * Send the code via the client.
   *
   * @return bool
   *   Where sending sms was successful.
   */
  public function sendCode() {
    $user = $this->entityTypeManager->getStorage('user')->load($this->context['uid']);
    $this->code = $this->smsPhoneNumberUtil->generateVerificationCode($this->codeLength);
    try {
      $message = $this->smsPhoneNumberConfig->get('tfa_message');
      $message = $message ? $message : SmsPhoneNumberUtilInterface::PHONE_NUMBER_DEFAULT_SMS_MESSAGE;
      if (!($this->verificationToken = $this->smsPhoneNumberUtil->sendVerification($this->phoneNumber, $message, $this->code, ['user' => $user]))) {
        return FALSE;
      }

      // @todo Consider storing date_sent or date_updated to inform user.
      $this->logger->info('TFA validation code sent to user @uid', ['@uid' => $this->context['uid']]);
      return TRUE;
    }
    catch (Exception $e) {
      $this->logger->error('Send message error to user @uid. Status code: @code, message: @message', [
        '@uid' => $this->context['uid'],
        '@code' => $e->getCode(),
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Verifies the given code with this session's verification token.
   *
   * @param string $code
   *   Code.
   *
   * @return bool
   *   Verification status.
   */
  public function verifyCode($code) {
    return $this->isValid = $this->smsPhoneNumberUtil->verifyCode($this->phoneNumber, $code, $this->verificationToken);
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbacks() {
    return ($this->pluginDefinition['fallbacks']) ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function purge() {}

}
