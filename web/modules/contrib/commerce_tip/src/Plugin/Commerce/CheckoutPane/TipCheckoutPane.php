<?php

namespace Drupal\commerce_tip\Plugin\Commerce\CheckoutPane;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_tip\CommerceTipUtilitiesInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the commerce tip checkout pane.
 *
 * @CommerceCheckoutPane(
 *   id = "commerce_tip_checkout_pane",
 *   label = @Translation("Tip"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class TipCheckoutPane extends CheckoutPaneBase {

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected InlineFormManager $inlineFormManager;

  /**
   * The router match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The commerce tip utilities.
   *
   * @var \Drupal\commerce_tip\CommerceTipUtilitiesInterface
   */
  protected CommerceTipUtilitiesInterface $commerceTipUtilities;

  /**
   * Constructs a new TipCheckoutPane object.
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
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $router_match
   *   The router match.
   * @param \Drupal\commerce_tip\CommerceTipUtilitiesInterface $commerce_tip_utilities
   *   The commerce tip utilities.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager, InlineFormManager $inline_form_manager, RouteMatchInterface $router_match, CommerceTipUtilitiesInterface $commerce_tip_utilities) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);
    $this->inlineFormManager = $inline_form_manager;
    $this->routeMatch = $router_match;
    $this->commerceTipUtilities = $commerce_tip_utilities;
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
      $container->get('plugin.manager.commerce_inline_form'),
      $container->get('current_route_match'),
      $container->get('commerce_tip.utilities')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $summary = [];
    if (empty($this->configuration['tip_description']['value'])) {
      $summary[] = $this->t('Tip description: Off');
    }
    else {
      $summary[] = $this->t('Tip description: On');
    }
    if (empty($this->configuration['tip_options'])) {
      $summary[] = $this->t('Tip options: NULL');
    }
    else {
      $summary[] = $this->t('Tip options: @tip_options', ['@tip_options' => $this->configuration['tip_options']]);
    }

    return implode('<br>', $summary);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'tip_description' => [
        'value' => '',
        'format' => 'plain_text',
      ],
      'tip_options' => 'none|None,0.01|1%,0.05|5%,0.1|10%,other|Other',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['tip_description'] = [
      '#type' => 'text_format',
      '#title' => t('Tip Description'),
      '#description' => t('Shown at the top of checkout pane.'),
      '#default_value' => $this->configuration['tip_description']['value'],
      '#format' => $this->configuration['tip_description']['format'],
    ];
    $form['tip_options'] = [
      '#type' => 'textarea',
      '#title' => t('Tip Options'),
      '#description' => t("Use the format 'percent|Percent'. For example: 'none|None,0.01|1%,0.05|5%,0.1|10%,other|Other'. The 'other' option allows users to input a custom tip amount, while 'none' is for ignoring the tip. If left blank, only an input field for the tip will be shown."),
      '#default_value' => $this->configuration['tip_options'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#parents'], 0, -2);
    $values = $form_state->getValue($parents);
    $tip_options = $this->commerceTipUtilities->convertTipOptions($values['tip_options']);
    if ($tip_options) {
      foreach ($tip_options as $key => $option) {
        if (!in_array($key, ['none', 'other']) && !is_numeric($key)) {
          $form_state->setError($form['tip_options'], t('Tip option must be number.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['tip_description'] = $values['tip_description'];
      $this->configuration['tip_options'] = $values['tip_options'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $step = $this->routeMatch->getParameter('step');
    if ($step && in_array($step, ['order_information', 'review'])) {
      $configuration = [
        'order_id' => $this->order->id(),
        'tip_description' => $this->configuration['tip_description'],
        'tip_options' => $this->configuration['tip_options'],
      ];
      $inline_form = $this->inlineFormManager->createInstance('commerce_tip_inline_form', $configuration);
      $pane_form['form'] = [
        '#parents' => array_merge($pane_form['#parents'], ['form']),
      ];
      $pane_form['form'] = $inline_form->buildInlineForm($pane_form['form'], $form_state);
    }
    else {
      $pane_form['form'] = [
        '#type' => 'label',
        '#title' => t('Commerce Tip only support step "order information" or "review".'),
      ];
    }
    return $pane_form;
  }

}
