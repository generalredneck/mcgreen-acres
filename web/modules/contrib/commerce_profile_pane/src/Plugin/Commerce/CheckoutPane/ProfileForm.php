<?php

declare(strict_types=1);

namespace Drupal\commerce_profile_pane\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_profile_pane\CheckoutPaneElementSubmit;
use Drupal\inline_entity_form\ElementSubmit;
use Drupal\inline_entity_form\WidgetSubmit;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a checkout pane with a form to edit the current user's profile.
 *
 * This is set to be disabled by default, as otherwise new profile types will
 * always automatically appear in checkout flows.
 *
 * NOTE: do not put this in the 'login' checkout step, as that does not provide
 * its own submit button.
 *
 * @CommerceCheckoutPane(
 *   id = "profile_form",
 *   label = @Translation("User profile form"),
 *   default_step = "_disabled",
 *   wrapper_element = "fieldset",
 *   deriver = "Drupal\commerce_profile_pane\Plugin\Derivative\ProfileFormCheckoutPaneDeriver",
 * )
 */
class ProfileForm extends CheckoutPaneBase {

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Language Manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Creates a new ProfileForm object.
   *
   * @phpcs:disable Drupal.Functions.MultiLineFunctionDeclaration.MissingTrailingComma
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CheckoutFlowInterface $checkout_flow,
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    AccountInterface $current_user,
    LanguageManagerInterface $language_manager
  ) {
    // phpcs:enable
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);

    $this->entityDisplayRepository = $entity_display_repository;
    $this->currentUser = $current_user;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('current_user'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'form_mode' => 'default',
      'display_label' => 'Edit profile',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $form_mode_options = $this->entityDisplayRepository->getFormModeOptions('profile');

    $summary = $this->t('Form mode: @mode', [
      '@mode' => $form_mode_options[$this->configuration['form_mode']],
    ]);
    $summary .= '<br>';

    $summary .= parent::buildConfigurationSummary();

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form_mode_options = $this->entityDisplayRepository->getFormModeOptions('profile');
    $form['form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form mode'),
      '#description' => $this->t("The form mode to display."),
      '#options' => $form_mode_options,
      '#default_value' => $this->configuration['form_mode'],
    ];

    // Let parent add to form.
    $form = parent::buildConfigurationForm($form, $form_state);

    // Change description of display_label.
    $form['display_label']['#description'] = $this->t('The label to display to the user on the profile form fieldset within the checkout form.');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['form_mode'] = $values['form_mode'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    // Do not show the pane if the user does not have access to edit their
    // profile or create a new one.
    $profile_type_id = $this->getProfileTypeId();

    // This will load the first profile found if there are multiple.
    $profile = $this->entityTypeManager->getStorage('profile')
      ->loadByUser($this->currentUser, $profile_type_id);

    if (empty($profile)) {
      $access_control_handler = $this->entityTypeManager
        ->getAccessControlHandler('profile');
      return $access_control_handler->createAccess($profile_type_id);
    }
    else {
      return $profile->access('update');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel() {
    return $this->configuration['display_label'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $profile_type_id = $this->getProfileTypeId();
    $profile_type = $this->entityTypeManager->getStorage('profile_type')->load($profile_type_id);

    // This will load the first profile found if there are multiple.
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    if (!$profile_type->get('multiple') && $this->currentUser->id() > 0) {
      $profile = $profile_storage->loadByUser($this->currentUser, $profile_type_id);
    }

    // Create a new profile entity if there is no profile for the user of this
    // type.
    if (empty($profile)) {
      $profile = $profile_storage->create([
        'type' => $profile_type_id,
        'uid' => $this->currentUser->id(),
        'langcode' => $profile_type->language()
          ? $profile_type->language()
          : $this->languageManager->getDefaultLanguage()->getId(),
      ]);
    }

    $pane_form['profile'] = [
      '#type' => 'inline_entity_form',
      '#entity_type' => 'profile',
      '#bundle' => $profile_type_id,
      '#form_mode' => $this->configuration['form_mode'],
      '#default_value' => $profile,
     /*
     // Not currently in use. See below.
     '#process' => [
       [
         \Drupal\inline_entity_form\Element\InlineEntityForm::class,
         'processEntityForm'
       ],
       // We need to add our own processor to run after the element's own
       // processor.
       [get_class($this), 'processEntityForm'],
     ],
     */
    ];

    // This should get done by setting InlineEntityForm::processEntityForm()
    // as a #process callback, but even allowing for the different action
    // buttons in this form, the values set on the submit button are gone by
    // the time we get to validation!!! WTF?
    // So as a dirty hack, hack them right in now...
    ElementSubmit::addCallback($complete_form['actions']['next'], $complete_form);

    return $pane_form;
  }

  /**
   * Form element process callback.
   *
   * **NOT CURRENTLY IN USE**
   */
  public static function processEntityForm($entity_form, FormStateInterface $form_state, &$complete_form) {
    // \Drupal\inline_entity_form\ElementSubmit::attach() will pass over the
    // pane form because its actions don't match what it expects to find.
    CheckoutPaneElementSubmit::attach($complete_form, $form_state);
    WidgetSubmit::attach($complete_form, $form_state);

    return $entity_form;
  }

  /**
   * Gets the profile type ID for this plugin.
   *
   * @return string
   *   The profile type ID.
   */
  protected function getProfileTypeId() {
    return $this->getDerivativeId();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [
      'config' => [
        // We can rely on the form mode depending on the profile type, so no
        // need to declare that here.
        'core.entity_form_mode.profile.' . $this->configuration['form_mode'],
      ],
    ];
  }

}
