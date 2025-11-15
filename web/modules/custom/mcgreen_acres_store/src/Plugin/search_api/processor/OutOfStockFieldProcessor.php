<?php

namespace Drupal\mcgreen_acres_store\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds a custom computed field to the Search API index.
 *
 * @SearchApiProcessor(
 *   id = "out_of_stock_field_processor",
 *   label = @Translation("Out Of Stock Field Processor"),
 *   description = @Translation("Adds an out of stock flag for product variations to the Search API index."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 * )
 */
class OutOfStockFieldProcessor extends ProcessorPluginBase {

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

          $stock_level = $variation->get('stock')->value;
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

}
