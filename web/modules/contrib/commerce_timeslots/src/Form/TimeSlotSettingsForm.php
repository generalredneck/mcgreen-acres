<?php

namespace Drupal\commerce_timeslots\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The TimeSlotSettingsForm class.
 *
 * @ingroup timeslot
 */
class TimeSlotSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new TimeSlotSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, TypedConfigManagerInterface $typed_config_manager) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_timeslots.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_timeslots_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    // Get time slots settings.
    $config = $this->config('commerce_timeslots.settings');

    $form['nr_days_range'] = [
      '#type' => 'number',
      '#title' => $this->t('Days range'),
      '#description' => $this->t('Provides the length of days range to display.'),
      '#default_value' => $config->get('nr_days_range') ?? 7,
      '#min' => 7,
      '#max' => 365,
    ];

    $form['nr_days_from'] = [
      '#type' => 'number',
      '#title' => $this->t('Starting from'),
      '#description' => $this->t('Starting from in days. The "0" means from today.'),
      '#default_value' => $config->get('nr_days_from') ?? 0,
      '#min' => 0,
      '#max' => 365,
    ];

    $checkout_flows = $this->entityTypeManager->getStorage('commerce_checkout_flow')->loadMultiple();

    $options = [];
    foreach ($checkout_flows as $flow_name => $flow) {
      $options[$flow_name] = $flow->label();
    }

    $form['checkout_flows'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Checkout flows'),
      '#description' => $this->t('Select the checkout flows where to place the pane.'),
      '#default_value' => $config->get('checkout_flows') ?? [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('commerce_timeslots.settings');
    $config->set('nr_days_range', $form_state->getValue('nr_days_range'));
    $config->set('nr_days_from', $form_state->getValue('nr_days_from'));
    $config->set('checkout_flows', $form_state->getValue('checkout_flows'));
    $config->save();
  }

}
