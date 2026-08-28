<?php

namespace Drupal\mcgreen_acres_store\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_product\Plugin\EntityReferenceSelection\ProductVariationSelection;

/**
 * Enables product variation selection by variation title, SKU, or title.
 *
 * Staff searching the "Add new item" autocomplete on an order naturally type
 * the product name (e.g. "egg"), not the variation title (e.g. "1 Dozen") or
 * SKU. A higher weight than commerce_product's default plugin makes
 * SelectionPluginManager::getPluginId() pick this one automatically for the
 * commerce_product_variation target type, with no field/handler override
 * needed since purchased_entity is a base field.
 */
#[EntityReferenceSelection(
  id: 'default:commerce_product_variation_with_product_title',
  label: new TranslatableMarkup('Product variation selection (including product title)'),
  group: 'default',
  weight: 2,
  entity_types: ['commerce_product_variation'],
)]
class ProductVariationWithProductTitleSelection extends ProductVariationSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $configuration = $this->getConfiguration();

    $query = $this->entityTypeManager->getStorage('commerce_product_variation')->getQuery();

    if (!empty($configuration['target_bundles'])) {
      $query->condition('type', $configuration['target_bundles'], 'IN');
    }

    if (isset($match)) {
      $match_condition = $query->orConditionGroup()
        ->condition('title', $match, $match_operator)
        ->condition('sku', $match, $match_operator)
        ->condition('product_id.entity.title', $match, $match_operator);
      $query->condition($match_condition);
    }

    // Add entity-access tag.
    $query
      ->accessCheck(TRUE)
      ->addTag('commerce_product_variation_access');

    // Add the Selection handler for system_query_entity_reference_alter().
    $query->addTag('entity_reference');
    $query->addMetaData('entity_reference_selection_handler', $this);

    // Add the sort option.
    if ($configuration['sort']['field'] !== '_none') {
      $query->sort($configuration['sort']['field'], $configuration['sort']['direction']);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    $options = [];
    $entities = $this->entityTypeManager->getStorage('commerce_product_variation')->loadMultiple($result);
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $entity */
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $label = $entity->getSku() . ': ' . $this->entityRepository->getTranslationFromContext($entity)->label();
      $product = $entity->getProduct();
      if ($product) {
        $label .= ' (' . $this->entityRepository->getTranslationFromContext($product)->label() . ')';
      }
      $options[$bundle][$entity_id] = Html::escape($label);
    }

    return $options;
  }

}
