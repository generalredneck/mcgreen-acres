<?php

namespace Drupal\commerce_tip\Plugin\Commerce\InlineForm;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce\Plugin\Commerce\InlineForm\InlineFormBase;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_tip\CommerceTipUtilitiesInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an inline form for Tip.
 *
 * @CommerceInlineForm(
 *   id = "commerce_tip_inline_form",
 *   label = @Translation("Tip InlineForm"),
 * )
 */
class TipInlineForm extends InlineFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected CurrencyFormatterInterface $currencyFormatter;

  /**
   * The commerce tip utilities.
   *
   * @var \Drupal\commerce_tip\CommerceTipUtilitiesInterface
   */
  protected CommerceTipUtilitiesInterface $commerceTipUtilities;

  /**
   * Constructs a new TipInlineForm object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $currency_formatter
   *   The currency formatter.
   * @param \Drupal\commerce_tip\CommerceTipUtilitiesInterface $commerce_tip_utilities
   *   The commerce tip utilities.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, CurrencyFormatterInterface $currency_formatter, CommerceTipUtilitiesInterface $commerce_tip_utilities) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currencyFormatter = $currency_formatter;
    $this->commerceTipUtilities = $commerce_tip_utilities;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('commerce_price.currency_formatter'),
      $container->get('commerce_tip.utilities')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      // The order_id is passed via configuration to avoid serializing the
      // order, which is loaded from scratch in the submit handler to minimize
      // chances of a conflicting save.
      'order_id' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function requiredConfiguration() {
    return ['order_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildInlineForm(array $inline_form, FormStateInterface $form_state) {
    $inline_form = parent::buildInlineForm($inline_form, $form_state);
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = $this->entityTypeManager->getStorage('commerce_order')->load($this->configuration['order_id']);
    if (!$order) {
      throw new \RuntimeException('Invalid order_id given to the coupon_redemption inline form.');
    }
    assert($order instanceof OrderInterface);
    $form_state->set('inline_configuration', $this->getConfiguration());
    $inline_form = [
      '#tree' => TRUE,
    ] + $inline_form;

    if ($this->configuration['tip_description'] && $this->configuration['tip_description']['value'] && $this->configuration['tip_description']['format']) {
      $inline_form['tip_description'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['tip-description-container'],
        ],
      ];
      $inline_form['tip_description']['description'] = [
        '#type' => 'processed_text',
        '#text' => $this->configuration['tip_description']['value'],
        '#format' => $this->configuration['tip_description']['format'],
      ];
    }
    $inline_form['tip_info'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['tip-info-container'],
      ],
    ];
    $configuration_tip_options = $this->configuration['tip_options'];
    $tip_options = $this->commerceTipUtilities->convertTipOptions($configuration_tip_options);
    if ($tip_options) {
      $inline_form['tip_info']['tip_options'] = [
        '#type' => 'hidden',
        '#value' => $tip_options,
      ];
      $inline_form['tip_info']['tip'] = [
        '#type' => 'radios',
        '#options' => $tip_options,
        '#attributes' => [
          'class' => ['tip_info_tip_checkboxes'],
        ],
      ];
      $inline_form['tip_info']['other_tip'] = [
        '#type' => 'number',
        '#min' => 0.01,
        '#step' => 0.01,
        '#default_value' => '1',
        '#placeholder' => t('Enter tip number'),
        '#attributes' => [
          'id' => 'tip_info_other_tip_number',
        ],
        '#states' => [
          'visible' => [
            ':input.tip_info_tip_checkboxes' => ['value' => 'other'],
          ],
        ],
        '#element_validate' => [
          [get_called_class(), 'validateNumber'],
        ],
      ];
    }
    else {
      $inline_form['tip_info']['tip'] = [
        '#type' => 'number',
        '#min' => 0.01,
        '#step' => 0.01,
        '#element_validate' => [
          [get_called_class(), 'validateNumber'],
        ],
      ];
    }

    $inline_form['tip_info']['add_tip'] = [
      '#type' => 'submit',
      '#value' => t('Add'),
      '#name' => 'add_tip',
      '#limit_validation_errors' => [
        $inline_form['#parents'],
      ],
      '#submit' => [[get_called_class(), 'addTip']],
      '#ajax' => [
        'callback' => [get_called_class(), 'ajaxRefreshForm'],
        'element' => $inline_form['#parents'],
      ],
    ];
    $adjustments = $order->getAdjustments();
    $hide_tip = FALSE;

    foreach ($adjustments as $index => $adjustment) {
      $type = $adjustment->getType();
      $label = $adjustment->getLabel();
      $sourceId = $adjustment->getSourceId();
      if ($type == 'tip'  && $sourceId == 'commerce_tip' && $label == 'Tip') {
        $hide_tip = TRUE;
        /** @var \Drupal\commerce_price\Price $amount */
        $amount = $adjustment->getAmount();
        $tip_value_formatter = $this->currencyFormatter->format($amount->getNumber(), $amount->getCurrencyCode());
        $inline_form[$index] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['tip-index--container'],
          ],
        ];
        $inline_form[$index]['tip_value'] = [
          '#type' => 'label',
          '#title' => $tip_value_formatter,
          '#title_display' => 'hidden',
        ];
        $inline_form[$index]['remove_tip'] = [
          '#type' => 'submit',
          '#value' => t('Remove'),
          '#name' => 'remove_tip_' . $index,
          '#ajax' => [
            'callback' => [get_called_class(), 'ajaxRefreshForm'],
            'element' => $inline_form['#parents'],
          ],
          '#weight' => 50,
          '#limit_validation_errors' => [
            $inline_form['#parents'],
          ],
          '#adjustment_index' => $index,
          '#submit' => [[get_called_class(), 'removeTip']],
          // Simplify ajaxRefresh() by having all triggering elements
          // on the same level.
          '#parents' => array_merge($inline_form['#parents'], ['remove_tip_' . $index]),
        ];
      }
    }
    if ($hide_tip) {
      // Don't allow additional tip to be added.
      $inline_form['tip_info']['tip']['#access'] = FALSE;
      $inline_form['tip_info']['other_tip']['#access'] = FALSE;
      $inline_form['tip_info']['add_tip']['#access'] = FALSE;
    }

    return $inline_form;
  }

  /**
   * Validates the number element.
   *
   * Converts the number back to the standard format (e.g. "9,99" -> "9.99").
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateNumber(array $element, FormStateInterface $form_state) {
    $value = trim($element['#value']);
    if (empty($value)) {
      $form_state->setError($element, t('You need to enter a value for other tip.'));
    }
    $title = t('Tip');
    $number_formatter = \Drupal::service('commerce_price.number_formatter');

    $value = $number_formatter->parse($value);
    if ($value === FALSE) {
      $form_state->setError($element, t('%title must be a number.', [
        '%title' => $title,
      ]));
      return;
    }
    if (isset($element['#min']) && $value < $element['#min']) {
      $form_state->setError($element, t('%title must be higher than or equal to %min.', [
        '%title' => $title,
        '%min' => $element['#min'],
      ]));
      return;
    }
    if (isset($element['#max']) && $value > $element['#max']) {
      $form_state->setError($element, t('%title must be lower than or equal to %max.', [
        '%title' => $title,
        '%max' => $element['#max'],
      ]));
      return;
    }

    $form_state->setValueForElement($element, $value);
  }

  /**
   * Submit callback for the "Add Tip" button.
   */
  public static function addTip(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#parents'], 0, -1);
    $values = $form_state->getValue($parents);
    $inline_configuration = $form_state->get('inline_configuration');
    $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($inline_configuration['order_id']);
    $total_price = $order->getTotalPrice();
    $tip = $values['tip'];
    if ($values['tip_options'] && $values['tip'] == 'other') {
      $tip = $values['other_tip'];
    }
    elseif ($values['tip_options'] && $values['tip'] !== 'none') {
      $tip = (float) $total_price->getNumber() * (float) $values['tip'];
    }
    if ($values['tip'] !== 'none' && !empty($tip)) {
      $order->addAdjustment(new Adjustment([
        'type' => 'tip',
        'label' => 'Tip',
        'amount' => new Price($tip, $total_price->getCurrencyCode()),
        'locked' => TRUE,
        'source_id' => 'commerce_tip',
        'percentage' => NULL,
        'included' => FALSE,
      ]))->save();
    }
    $form_state->setRebuild();
  }

  /**
   * Submit callback for the "Remove tip" button.
   */
  public static function removeTip(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $inline_configuration = $form_state->get('inline_configuration');
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($inline_configuration['order_id']);
    $adjustment_index = $triggering_element['#adjustment_index'];
    $order->get('adjustments')->removeItem($adjustment_index);
    $order->save();
    $form_state->setRebuild();
  }

}
