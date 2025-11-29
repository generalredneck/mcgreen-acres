<?php

namespace Drupal\mcgreen_acres_store\Plugin\search_api\processor;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\commerce_stock\StockServiceManager;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a custom computed field to the Search API index.
 *
 * @SearchApiProcessor(
 *   id = "out_of_stock_field_processor",
 *   label = @Translation("Out Of Stock Field Processor"),
 *   description = @Translation("Adds an out of stock flag for product variations to the Search API index."),
 *   stages = {
 *     "add_properties" = 0,
 *     "preprocess_index" = 0,
 *   },
 * )
 */
class OutOfStockFieldProcessor extends ProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The stock service manager.
   *
   * @var \Drupal\commerce_stock\StockServiceManager
   */
  protected $stockServiceManager;

  /**
   * Constructs an OutOfStockFieldProcessor object.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be provided
   *   to the plugin by the plugin manager for convenience.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_stock\StockServiceManager $stockServiceManager
   *   The commerce stock service manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StockServiceManager $stockServiceManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stockServiceManager = $stockServiceManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('commerce_stock.service_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Out of Stock'),
        'description' => $this->t('Indicates whether the product variation is out of stock.'),
        'type' => 'boolean',
        'is_list' => FALSE,
        'processor_id' => $this->getPluginId(),
      ];
      $properties['out_of_stock'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    if ($item->getDatasourceId() === 'entity:commerce_product') {
      /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
      $product = $item->getOriginalObject()->getEntity();
      $is_out_of_stock = FALSE;
      $variations = $product->getVariations();
      $out_of_stock_tracker = [];
      foreach ($variations as $key => $variation) {
        if ($variation->hasField('commerce_stock_always_in_stock')
          && $variation->get('commerce_stock_always_in_stock')->value == FALSE
          && $variation->hasField('stock')
          && !$variation->get('stock')->isEmpty()) {

          $stock_level = $this->stockServiceManager->getStockLevel($variation);
          $out_of_stock_tracker[$key] = ($stock_level <= 0);
        }
        else {
          $out_of_stock_tracker[$key] = FALSE;
        }
      }
      // If all variations are out of stock, mark the product as out of stock.
      $is_out_of_stock = in_array(FALSE, $out_of_stock_tracker, TRUE) === FALSE;
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'out_of_stock');
      foreach ($fields as $field) {
        $field->addValue($is_out_of_stock);
      }
    }
  }

  /**
   *
   */
  public function preprocessIndexItems(array $items) {
    foreach ($items as $item) {
      $this->addFieldValues($item);
    }
  }

}
