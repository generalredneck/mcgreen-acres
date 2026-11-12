<?php

namespace Drupal\commerce_custom_checkout_message\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide a custom message panel in commerce checkout flow.
 *
 * @CommerceCheckoutPane(
 *   id = "custom_checkout_message",
 *   label = @Translation("Custom checkout message"),
 *   display_label = @Translation("Message"),
 *   default_step = "_disabled",
 *   wrapper_element = "container",
 * )
 */
class CustomCheckoutMessage extends CheckoutPaneBase {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs a new CustomCheckoutMessage object.
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
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager, Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);
    $this->token = $token;
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
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'message' => [
        'value'  => "",
        'format' => 'plain_text',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $message = $this->token->replace($this->configuration['message']['value']);
    $message = Unicode::truncate(strip_tags($message), 100, FALSE, TRUE);
    $summary = 'Format: ' . $this->configuration['message']['format'] . '<br />';
    $summary .= $message;
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $message = $this->token->replace($this->configuration['message']['value'], [
      'commerce_order' => $this->order,
    ]);
    $pane_form['message'] = [
      '#markup' => Markup::create($message),
    ];
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['message'] = [
      '#type'          => 'text_format',
      '#title'         => $this->t('Message'),
      '#description'   => $this->t('Provide a custom message in checkout flow.'),
      '#default_value' => $this->configuration['message']['value'],
      '#format'        => $this->configuration['message']['format'],
      '#required'      => TRUE,
    ];
    $form['token_help'] = [
      '#theme' => 'token_tree_link',
      '#text'  => $this->t('you can use any available tokens.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['message'] = $values['message'];
    }
  }

}
