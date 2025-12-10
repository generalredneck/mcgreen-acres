<?php

namespace Drupal\commerce_timeslots\Plugin\Commerce\CheckoutPane;

use Drupal\commerce\AjaxFormTrait;
use Drupal\commerce_checkout\CheckoutOrderManagerInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_timeslots\Services\CommerceTimeSlots;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Display the time slot panel pane.
 *
 * @CommerceCheckoutPane(
 *   id = "timeslot_pane",
 *   label = @Translation("Time Slot"),
 *   display_label = @Translation("Time Slot"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class TimeSlotPane extends CheckoutPaneBase {

  use AjaxFormTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The time slot service.
   *
   * @var \Drupal\commerce_timeslots\Services\CommerceTimeSlots
   */
  protected CommerceTimeSlots $timeSlotManager;

  /**
   * The order manager.
   *
   * @var \Drupal\commerce_checkout\CheckoutOrderManagerInterface
   */
  protected CheckoutOrderManagerInterface $orderManager;

  /**
   * Constructs a new TimeSlotPane instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow
   *   The parent checkout flow.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\commerce_timeslots\Services\CommerceTimeSlots $timeslot_manager
   *   The time slot service.
   * @param \Drupal\commerce_checkout\CheckoutOrderManagerInterface $order_manager
   *   The order manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CheckoutFlowInterface $checkout_flow,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    CommerceTimeSlots $timeslot_manager,
    CheckoutOrderManagerInterface $order_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);

    $this->configFactory = $config_factory;
    $this->timeSlotManager = $timeslot_manager;
    $this->orderManager = $order_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('commerce_timeslots.timeslots'),
      $container->get('commerce_checkout.checkout_order_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $time_slot_id = $this->configuration['time_slot'];
    if (is_numeric($time_slot_id)) {
      $data = (array) $this->order->getData('time_slot');
      $data['order_id'] = $this->order->id();
      $time_slot = $this->timeSlotManager->getForm($time_slot_id, $data);
    }
    if (empty($time_slot)) {
      $time_slot = [
        '#markup' => $this->t('The time slot is not configured.'),
      ];
    }
    $pane_form['time_slot'] = $time_slot;
    return $pane_form;
  }

  /**
   * Set the visibility control.
   *
   * @return bool
   *   If is TRUE, the pane will be displayed. If FALSE, then not.
   */
  public function isVisible(): bool {
    // Get admin API configs.
    $config = $this->configFactory->get('commerce_timeslots.settings');
    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
    $checkout_flow = $this->orderManager->getCheckoutFlow($this->order);
    // Get the configured checkout flows to share the pane with.
    $checkout_flows = $config->get('checkout_flows');

    if (empty($checkout_flows) || (!empty($checkout_flows) && !in_array($checkout_flow->id(), $checkout_flows))) {
      return FALSE;
    }

    // The order must contain at least one shippable purchasable entity.
    foreach ($this->order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if ($purchased_entity && $purchased_entity->hasField('weight')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    // Get the selected time slot from the pane.
    $order_time_slot = $this->order->getData('time_slot');

    if ($order_time_slot) {
      $time_slot_config = $order_time_slot['time_slot']['wrapper'];
    }

    if (empty($time_slot_config)) {
      return $this->t('There is no available timeslot.');
    }

    $date = $time_slot_config['date'];
    $time = $time_slot_config['time'];
    // Convert the data into a more readable way.
    $time_slot_formated = $this->timeSlotManager->getTimeSlotToArray($time, $date);

    return $this->t(
      'Selected time slot: @date, @time',
      [
        '@date' => $time_slot_formated['date'],
        '@time' => $time_slot_formated['time'],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['time_slot'] = $values['time_slot'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'time_slot' => !empty($this->configuration['time_slot']) ? $this->configuration['time_slot'] : 'none',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Get all time slots and then build the available options.
    $time_slots = $this->timeSlotManager->getAllTimeSlots();
    $options = ['none' => $this->t('None')];
    foreach ($time_slots as $time_slot) {
      $options[$time_slot->id()] = $time_slot->label();
    }

    $form['time_slot'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Select a time slot'),
      '#default_value' => $this->configuration['time_slot'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $time_slot = $this->t('None');
    if (!empty($this->configuration['time_slot']) && is_numeric($this->configuration['time_slot'])) {
      $time_slot = $this->timeSlotManager->getTimeSlot($this->configuration['time_slot']);
      $time_slot = $time_slot->label();
    }
    return $this->t(
      'Selected time slot: @time_slot',
      ['@time_slot' => $time_slot]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $this->order->setData('time_slot', $values);
    $this->order->setData('time_slot_id', $this->configuration['time_slot']);
  }

}
