<?php

namespace Drupal\commerce_email\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity\Form\EntityDuplicateFormTrait;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for creating and editing email entities.
 */
class EmailForm extends EntityForm {

  use EntityDuplicateFormTrait;

  /**
   * The email event plugin manager.
   *
   * @var \Drupal\Core\Plugin\DefaultPluginManager
   */
  protected $emailEventManager;

  /**
   * The log category manager.
   *
   * @var \Drupal\commerce_log\LogCategoryManagerInterface
   */
  protected $logCategoryManager;

  /**
   * The log template manager.
   *
   * @var \Drupal\commerce_log\LogTemplateManagerInterface
   */
  protected $logTemplateManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->setEntityTypeManager($container->get('entity_type.manager'));
    $instance->setModuleHandler($container->get('module_handler'));
    $instance->emailEventManager = $container->get('plugin.manager.commerce_email_event');
    if ($container->has('plugin.manager.commerce_log_category')) {
      $instance->logCategoryManager = $container->get('plugin.manager.commerce_log_category');
    }
    if ($container->has('plugin.manager.commerce_log_template')) {
      $instance->logTemplateManager = $container->get('plugin.manager.commerce_log_template');
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\commerce_email\Entity\EmailInterface $email */
    $email = $this->entity;
    $events = $this->emailEventManager->getDefinitions();
    $event_options = $this->getEventOptions($events);
    $selected_event_id = $form_state->getValue('event', $email->getEventId());

    $wrapper_id = Html::getUniqueId('payment-gateway-form');
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';
    $form['#tree'] = TRUE;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $email->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $email->id(),
      '#machine_name' => [
        'exists' => '\Drupal\commerce_email\Entity\Email::load',
      ],
      '#disabled' => !$email->isNew(),
    ];
    $form['event'] = [
      '#type' => 'select',
      '#title' => $this->t('Event'),
      '#default_value' => $selected_event_id,
      '#options' => $event_options,
      '#required' => TRUE,
      '#disabled' => !$email->isNew(),
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => $wrapper_id,
      ],
      '#access' => count($event_options) > 1,
    ];
    if (!$selected_event_id) {
      return $form;
    }
    /** @var \Drupal\commerce_email\Plugin\Commerce\EmailEvent\EmailEventInterface $event */
    $event = $this->emailEventManager->createInstance($selected_event_id);
    $target_entity_type_id = $event->getEntityTypeId();
    $token_types = array_merge([$target_entity_type_id], $event->getRelatedEntityTypeIds());
    $form_state->set('target_entity_type_id', $target_entity_type_id);

    // These addresses can't use the "email" element type because they
    // might contain tokens (which wouldn't pass validation).
    $form['from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From'),
      '#maxlength' => 255,
      '#default_value' => $email->getFrom(),
      '#required' => TRUE,
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['toType'] = [
      '#type' => 'radios',
      '#title' => $this->t('Send this email to'),
      '#default_value' => $email->getToType() ?? 'email',
      '#options' => [
        'email' => $this->t('Specific email address'),
        'role' => $this->t('Users with a role'),
      ],
      '#required' => TRUE,
    ];
    $form['to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#maxlength' => 255,
      '#default_value' => $email->getTo(),
      '#required' => TRUE,
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
      '#states' => [
        'visible' => [
          ':input[name="toType"]' => ['value' => 'email'],
        ],
        'enabled' => [
          ':input[name="toType"]' => ['value' => 'email'],
        ],
        'required' => [
          ':input[name="toType"]' => ['value' => 'email'],
        ],
      ],
    ];
    $roles = array_map(function ($role) {
      return $role->label();
    }, $this->entityTypeManager->getStorage('user_role')->loadMultiple());
    unset($roles[RoleInterface::AUTHENTICATED_ID], $roles[RoleInterface::ANONYMOUS_ID]);
    $form['toRole'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#options' => $roles,
      '#default_value' => $email->getToRole(),
      '#states' => [
        'visible' => [
          ':input[name="toType"]' => ['value' => 'role'],
        ],
        'enabled' => [
          ':input[name="toType"]' => ['value' => 'role'],
        ],
        'required' => [
          ':input[name="toType"]' => ['value' => 'role'],
        ],
      ],
    ];
    $form['cc'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cc'),
      '#maxlength' => 255,
      '#default_value' => $email->getCc(),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['bcc'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bcc'),
      '#maxlength' => 255,
      '#default_value' => $email->getBcc(),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['replyTo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reply to'),
      '#description' => $this->t('The address to which the reply will be sent.'),
      '#maxlength' => 255,
      '#default_value' => $email->getReplyTo(),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#maxlength' => 255,
      '#default_value' => $email->getSubject(),
      '#required' => TRUE,
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Body'),
      '#default_value' => $email->getBody(),
      '#rows' => 10,
      '#format' => $email->getBodyFormat(),
      '#required' => TRUE,
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => $token_types,
    ];
    $form['conditions'] = [
      '#type' => 'commerce_conditions',
      '#title' => $this->t('Conditions'),
      '#parent_entity_type' => 'commerce_email',
      '#entity_types' => [$target_entity_type_id],
      '#default_value' => $email->get('conditions'),
    ];
    $form['conditionOperator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Condition operator'),
      '#title_display' => 'invisible',
      '#options' => [
        'AND' => $this->t('All conditions must pass'),
        'OR' => $this->t('Only one condition must pass'),
      ],
      '#default_value' => $email->getConditionOperator(),
    ];
    $form['queue'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Queue these emails instead of sending them immediately.'),
      '#default_value' => $email->shouldQueue(),
    ];
    if ($this->moduleHandler->moduleExists('commerce_log')) {
      // Only show the logToEntity checkbox if the entity has a log category.
      $log_category_definitions = $this->logCategoryManager->getDefinitionsByEntityType($target_entity_type_id);
      $form['logToEntity'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Log sends of this email to the relevant entity.'),
        '#default_value' => !empty($log_category_definitions) && $email->getLogToEntity() ? 1 : 0,
        '#access' => !empty($log_category_definitions),
        '#description' => $this->t("Order emails have a generic log template to use if email specific templates don't exist, but you must define templates with the IDs below for other entity types:<br />mail_@email_id, mail_@email_id_failure", ['@email_id' => $email->id()]),
      ];
    }
    $form['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        0 => $this->t('Disabled'),
        1  => $this->t('Enabled'),
      ],
      '#default_value' => (int) $email->status(),
    ];

    return $form;
  }

  /**
   * Gets the event options from the events.
   *
   * @param array $events
   *   The events.
   *
   * @return array
   *   The array of the event options.
   */
  protected function getEventOptions(array $events): array {
    $event_options = [];
    $optgroup = $this->t('Other events')->render();

    foreach ($events as $id => $event) {
      if (isset($event['group_name'])) {
        $group_name = $event['group_name']->render();
        $event_options[$group_name][$id] = $event['label'];
      }
      else {
        $event_options[$optgroup][$id] = $event['label'];
      }
    }

    ksort($event_options);
    foreach ($event_options as $key => $options) {
      asort($event_options[$key]);
    }

    return $event_options;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $save = $this->entity->save();
    $this->postSave($this->entity, $this->operation);
    $this->messenger()->addMessage($this->t('Saved the %label email.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.commerce_email.collection');
    return $save;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email_id = $this->getEntity()->id();

    if (!$form_state->getValue('logToEntity') || empty($email_id)) {
      return;
    }

    $target_entity_type_id = $form_state->get('target_entity_type_id');
    if (empty($target_entity_type_id) || $target_entity_type_id === 'commerce_order') {
      return;
    }

    $definitions = $this->logTemplateManager->getDefinitions();
    $success_result_template_id = 'mail_' . $email_id;
    if (!isset($definitions[$success_result_template_id])) {
      $form_state->setErrorByName('logToEntity', $this->t('To enable send logging, you must first define a log template in your codebase with the following ID: @id', [
        '@id' => $success_result_template_id,
      ]));
    }

    $failed_result_template_id = 'mail_' . $email_id . '_failure';
    if (!isset($definitions[$failed_result_template_id])) {
      $form_state->setErrorByName('logToEntity', $this->t('To enable send logging, you must first define a log template in your codebase with the following ID: @id', [
        '@id' => $failed_result_template_id,
      ]));
    }
  }

}
