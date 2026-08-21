<?php

namespace Drupal\ms_clarity\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ms_clarity\Helpers\MicrosoftClarityAccounts;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Miscrosoft Clarity settings.
 */
class MicrosoftClarityAdminSettingsForm extends ConfigFormBase {

  /**
   * The manages modules.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Microsoft Clarity account manager.
   *
   * @var \Drupal\ms_clarity\Helpers\MicrosoftClarityAccounts
   */
  protected $msAccounts;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ms_clarity.settings'];
  }

  /**
   * Callback for both ajax account buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function mstagFieldCallback(array &$form, FormStateInterface $form_state) {
    return $form['general'];
  }

  /**
   * The constructor method.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The manages modules.
   * @param \Drupal\ms_clarity\Helpers\MicrosoftClarityAccounts $ms_clarity_accounts
   *   The Microsoft Account Manager accounts manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    AccountInterface $current_user,
    ModuleHandlerInterface $module_handler,
    MicrosoftClarityAccounts $ms_clarity_accounts,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->currentUser = $current_user;
    $this->moduleHandler = $module_handler;
    $this->msAccounts = $ms_clarity_accounts;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('ms_clarity.accounts'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'microsoft_clarity_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ms_clarity.settings');

    $id = $this->msAccounts->getProjectId();

    $form['general'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Clarity ID'),
      '#description' => $this->t('Before you can start learning how people are using your site,You need to enter the project id. To get project id,<a href = ":microsoft" target = "_blank"> register your siter with Microsoft Clarity </a>, or if you already have registered your site, go to your Microsoft Clarity Settings page to get the project id from tracking Code', [':microsoft' => 'https://clarity.microsoft.com/']),
    ];

    $form['general']['accounts'] = [
      '#default_value' => $id ,
      '#maxlength' => 20,
      '#required' => TRUE,
      '#size' => 20,
      '#type' => 'textfield',
      '#element_validate' => [[get_class($this), 'mstagElementValidate']],

    ];

    // Visibility settings.
    $form['Tracking Options'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Tracking Options'),
      '#attached' => [
        'library' => [
          'ms_clarity/ms_clarity.admin',
        ],
      ],
    ];

    $visibility_request_path_pages = $config->get('visibility.request_path_pages');

    $form['tracking']['page_visibility_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Pages'),
      '#group' => 'Tracking Options',
    ];

    if ($config->get('visibility.request_path_mode') == 2) {
      $form['tracking']['page_visibility_settings'] = [];
      $form['tracking']['page_visibility_settings']['ms_clarity_visibility_request_path_mode'] =
      [
        '#type' => 'value',
        '#value' => 2,
      ];
      $form['tracking']['page_visibility_settings']['ms_clarity_visibility_request_path_pages'] =
      [
        '#type' => 'value',
        '#value' => $visibility_request_path_pages,
      ];
    }
    else {
      $options = [
        $this->t('Every page except the listed pages'),
        $this->t('The listed pages only'),
      ];
      $description = $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are %blog for the blog page and %blog-wildcard for every personal blog. %front is the front page.",
       [
         '%blog' => '/blog',
         '%blog-wildcard' => '/blog/*',
         '%front' => '<front>',
       ]
      );

      $form['tracking']['page_visibility_settings']['ms_clarity_visibility_request_path_mode'] = [
        '#type' => 'radios',
        '#title' => $this->t('Add tracking to specific pages'),
        '#options' => $options,
        '#default_value' => $config->get('visibility.request_path_mode') ? $config->get('visibility.request_path_mode') : 0,
      ];
      $form['tracking']['page_visibility_settings']['ms_clarity_visibility_request_path_pages'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Pages'),
        '#title_display' => 'invisible',
        '#default_value' => !empty($visibility_request_path_pages) ? $visibility_request_path_pages : '',
        '#description' => $description,
        '#rows' => 10,
      ];
    }

    // Render the role overview.
    $visibility_user_role_roles = $config->get('visibility.user_role_roles');

    $form['tracking']['role_visibility_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Roles'),
      '#group' => 'Tracking Options',
    ];

    $form['tracking']['role_visibility_settings']['ms_clarity_visibility_user_role_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Add tracking for specific roles'),
      '#options' => [
        $this->t('Add to the selected roles only'),
        $this->t('Add to every role except the selected ones'),
      ],
      '#default_value' => $config->get('visibility.user_role_mode') ? $config->get('visibility.user_role_mode') : 0,
    ];

    // Get the available roles.
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $role_options = array_map(function (RoleInterface $role) {
      return $role->label();
    }, $roles);

    $form['tracking']['role_visibility_settings']['ms_clarity_visibility_user_role_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#default_value' => !empty($visibility_user_role_roles) ? $visibility_user_role_roles : [],
      '#options' => array_map('\Drupal\Component\Utility\Html::escape', $role_options),
      '#description' => $this->t('If none of the roles are selected, all users will be tracked. If a user has any of the roles checked, that user will be tracked (or excluded, depending on the setting above).'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $form_state->setValue('ms_clarity_visibility_request_path_pages', trim($form_state->getValue('ms_clarity_visibility_request_path_pages')));
    $form_state->setValue('ms_clarity_visibility_user_role_roles', array_filter($form_state->getValue('ms_clarity_visibility_user_role_roles')));

    // Verify that every path is prefixed with a slash, but don't check PHP
    // code snippets and do not check for slashes if no paths configured.
    if ($form_state->getValue('ms_clarity_visibility_request_path_mode') != 2 && !empty($form_state->getValue('ms_clarity_visibility_request_path_pages'))) {
      $pages = preg_split('/(\r\n?|\n)/', $form_state->getValue('ms_clarity_visibility_request_path_pages'));
      foreach ($pages as $page) {
        if (strpos($page, '/') !== 0 && $page !== '<front>') {
          $form_state->setErrorByName('ms_clarity_visibility_request_path_pages', $this->t('Path "@page" not prefixed with slash.', ['@page' => $page]));
          // Drupal forms show one error only.
          break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ms_clarity.settings');
    $accounts = $form_state->getValue('accounts');

    $config
      ->set('account', $accounts)
      ->set('visibility.request_path_mode', $form_state->getValue('ms_clarity_visibility_request_path_mode'))
      ->set('visibility.request_path_pages', $form_state->getValue('ms_clarity_visibility_request_path_pages'))
      ->set('visibility.user_role_mode', $form_state->getValue('ms_clarity_visibility_user_role_mode'))
      ->set('visibility.user_role_roles', $form_state->getValue('ms_clarity_visibility_user_role_roles'))
      ->save();
    if ($form_state->hasValue('ms_clarity_translation_set')) {
      $config->set('translation_set', $form_state->getValue('ms_clarity_translation_set'))->save();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Validation function for Project ID.
   */
  public static function mstagElementValidate(&$element, FormStateInterface $form_state) {
    if (empty($element['#value'])) {
      return;
    }
    if (!preg_match('/^[a-zA-Z0-9]+$/', $element['#value'])) {
      $form_state->setErrorByName('ms_clarity_account', t('Your project id Is not Valid, Please enter a valid id'));
    }
  }

}
