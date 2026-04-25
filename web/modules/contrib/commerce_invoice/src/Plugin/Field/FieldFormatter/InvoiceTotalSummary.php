<?php

namespace Drupal\commerce_invoice\Plugin\Field\FieldFormatter;

use Drupal\commerce_invoice\InvoiceTotalSummaryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_invoice_total_summary' formatter.
 *
 * @FieldFormatter(
 *   id = "commerce_invoice_total_summary",
 *   label = @Translation("Invoice total summary"),
 *   field_types = {
 *     "commerce_price",
 *   },
 * )
 */
class InvoiceTotalSummary extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The invoice total summary service.
   *
   * @var \Drupal\commerce_invoice\InvoiceTotalSummaryInterface
   */
  protected $invoiceTotalSummary;

  /**
   * Constructs a new InvoiceTotalSummary object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\commerce_invoice\InvoiceTotalSummaryInterface $invoice_total_summary
   *   The invoice total summary service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, InvoiceTotalSummaryInterface $invoice_total_summary) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->invoiceTotalSummary = $invoice_total_summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('commerce_invoice.invoice_total_summary')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $items->getEntity();
    $elements = [];
    if (!$items->isEmpty()) {
      $elements[0] = [
        '#theme' => 'commerce_invoice_total_summary',
        '#invoice_entity' => $invoice,
        '#totals' => $this->invoiceTotalSummary->buildTotals($invoice),
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return $entity_type == 'commerce_invoice' && $field_name == 'total_price';
  }

}
