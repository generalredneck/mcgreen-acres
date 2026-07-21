<?php

namespace Drupal\commerce_invoice\Form;

use Drupal\commerce\EntityHelper;
use Drupal\commerce\Form\CommerceBundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity\Form\EntityDuplicateFormTrait;
use Drupal\language\Entity\ContentLanguageSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an invoice type form.
 */
class InvoiceTypeForm extends CommerceBundleEntityFormBase {

  use EntityDuplicateFormTrait;

  /**
   * The workflow manager.
   *
   * @var \Drupal\state_machine\WorkflowManagerInterface
   */
  protected $workflowManager;

  /**
   * The File system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->workflowManager = $container->get('plugin.manager.workflow');
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\commerce_invoice\Entity\InvoiceTypeInterface $invoice_type */
    $invoice_type = $this->entity;
    $workflows = $this->workflowManager->getGroupedLabels('commerce_invoice');
    $number_pattern_storage = $this->entityTypeManager->getStorage('commerce_number_pattern');
    $number_patterns = $number_pattern_storage->loadByProperties(['targetEntityType' => 'commerce_invoice']);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $invoice_type->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $invoice_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\commerce_invoice\Entity\InvoiceType::load',
        'source' => ['label'],
      ],
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#disabled' => !$invoice_type->isNew(),
    ];
    $form['workflow'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow'),
      '#options' => $workflows,
      '#default_value' => $invoice_type->getWorkflowId(),
      '#description' => $this->t('Used by all invoices of this type.'),
    ];
    $form['numberPattern'] = [
      '#type' => 'select',
      '#title' => $this->t('Number pattern'),
      '#options' => EntityHelper::extractLabels($number_patterns),
      '#default_value' => $invoice_type->getNumberPatternId(),
    ];

    $form['logo_file'] = [
      '#title' => $this->t('Logo'),
      '#type' => 'managed_file',
      '#upload_location' => 'public://commerce_invoice/',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg svg'],
      ],
    ];

    if ($file = $invoice_type->getLogoFile()) {
      $form['logo_file']['#default_value'] = ['target_id' => $file->id()];
    }

    $form['emails'] = [
      '#type' => 'details',
      '#title' => $this->t('Emails'),
      '#weight' => 5,
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#tree' => FALSE,
    ];
    $form['emails']['notice'] = [
      '#markup' => '<p>' . $this->t('Emails are sent in the HTML format. You will need a module such as <a href="https://www.drupal.org/project/symfony_mailer">Symfony Mailer</a> to send HTML emails.') . '</p>',
    ];
    $form['emails']['sendConfirmation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Email the customer a confirmation when an invoice is "confirmed" or paid'),
      '#default_value' => $invoice_type->isNew() || $invoice_type->shouldSendConfirmation(),
    ];
    $form['emails']['confirmationBcc'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Send a copy of the confirmation to this email:'),
      '#default_value' => ($invoice_type->isNew()) ? '' : $invoice_type->getConfirmationBcc(),
      '#states' => [
        'visible' => [
          ':input[name="sendConfirmation"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $token_types = ['commerce_invoice'];
    $form['payment-terms'] = [
      '#type' => 'details',
      '#title' => $this->t('Payment terms'),
      '#tree' => FALSE,
      '#open' => TRUE,
    ];
    $form['payment-terms']['dueDays'] = [
      '#type' => 'number',
      '#size' => 3,
      '#description' => $this->t("used to determine the invoice's due date."),
      '#field_suffix' => $this->t('days'),
      '#title' => $this->t('Due date'),
      '#default_value' => $invoice_type->getDueDays(),
    ];
    $form['payment-terms']['paymentTerms'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Payment terms'),
      '#default_value' => $invoice_type->getPaymentTerms(),
      '#description' => $this->t('The payment terms.'),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['payment-terms']['payment_terms_token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => $token_types,
    ];
    $form['footerText'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Footer text'),
      '#default_value' => $invoice_type->getFooterText(),
      '#description' => $this->t('Text to display in the footer of the invoice.'),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['footer_text_token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => $token_types,
    ];
    $form['privateSubdirectory'] = [
      '#type' => 'textfield',
      '#field_prefix' => 'private://',
      '#title' => $this->t('Private subdirectory'),
      '#description' => $this->t('The subdirectory under the private file system where invoices of this type will be stored. Leave blank to store invoices directly in the root of the private directory.'),
      '#default_value' => (string) $invoice_type->getPrivateSubdirectory(),
    ];
    $form = $this->buildTraitForm($form, $form_state);

    if ($this->moduleHandler->moduleExists('language')) {
      $form['language'] = [
        '#type' => 'details',
        '#title' => $this->t('Language settings'),
      ];
      $form['language']['language_configuration'] = [
        '#type' => 'commerce_invoice_language_configuration',
        '#entity_information' => [
          'entity_type' => 'commerce_invoice',
          'bundle' => $invoice_type->id(),
        ],
        '#default_value' => ContentLanguageSettings::loadByEntityTypeBundle('commerce_invoice', $invoice_type->id()),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->validateTraitForm($form, $form_state);
    /** @var \Drupal\commerce_invoice\Entity\InvoiceTypeInterface $invoice_type */
    $invoice_type = $this->entity;

    $logo_file = $form_state->getValue(['logo_file', '0']);
    /** @var \Drupal\file\Entity\File $file */
    if (!empty($logo_file) && $file = $this->entityTypeManager->getStorage('file')->load($logo_file)) {
      $file->setPermanent();
      $file->save();
      $invoice_type->setLogo($file->uuid());
    }
    else {
      $invoice_type->setLogo(NULL);
    }
    $private_subdirectory = $form_state->getValue('privateSubdirectory');
    if (!empty($private_subdirectory)) {
      $private_subdirectory = 'private://' . $private_subdirectory;
      $is_writable = $this->fileSystem->prepareDirectory($private_subdirectory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      if (!$is_writable) {
        $form_state->setErrorByName('privateSubdirectory', $this->t('The private subdirectory %private_subdirectory is not writable.', ['%private_subdirectory' => $private_subdirectory]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $save = $this->entity->save();
    $this->postSave($this->entity, $this->operation);
    $this->submitTraitForm($form, $form_state);
    $this->messenger()->addMessage($this->t('Saved the %label invoice type.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.commerce_invoice_type.collection');

    return $save;
  }

}
