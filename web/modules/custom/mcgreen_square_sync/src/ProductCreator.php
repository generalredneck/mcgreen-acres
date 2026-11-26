<?php

namespace Drupal\mcgreen_square_sync;

use Drupal\commerce_price\Price;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Creates Drupal products for Square items that have none.
 *
 * Products start unpublished, so nothing appears in the store until staff have
 * finished setting them up.
 */
class ProductCreator {

  /**
   * The product and variation type new products get.
   */
  public const TYPE = 'physical';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
  ) {}

  /**
   * Returns the names that existing products already use.
   *
   * @param array[] $items
   *   Square items, each with a "name".
   *
   * @return string[]
   *   The names that are taken.
   */
  public function getTakenNames(array $items): array {
    $storage = $this->entityTypeManager->getStorage('commerce_product');
    $names = array_unique(array_map(fn(array $item) => $this->productName($item), $items));
    return array_values(array_filter($names, fn(string $name) => (bool) $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', $name)
      ->range(0, 1)
      ->execute()));
  }

  /**
   * Creates the products, one for each item name.
   *
   * Several variations of one Square item share a product.
   *
   * @param array[] $items
   *   Square items keyed by catalog object ID, each with "name",
   *   "variation_name", "unit", "price" (cents for one unit) and "currency".
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface[]
   *   The new variations, keyed by catalog object ID.
   */
  public function create(array $items): array {
    /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
    $store_storage = $this->entityTypeManager->getStorage('commerce_store');
    $store = $store_storage->loadDefault();
    $variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');

    $groups = [];
    foreach ($items as $id => $item) {
      $groups[$this->productName($item)][$id] = $item;
    }

    $ids = [];
    $transaction = $this->database->startTransaction();
    try {
      foreach ($groups as $name => $group) {
        $variations = [];
        foreach ($group as $catalog_id => $item) {
          $title = $this->variationTitle($item);
          $variation = $variation_storage->create([
            'type' => self::TYPE,
            'title' => $title,
            'sku' => $this->uniqueSku($name . ' ' . $title),
            'price' => new Price(sprintf('%.2f', $item['price'] / 100), $item['currency']),
          ]);
          $variation->save();
          $variations[] = $variation;
          $ids[$catalog_id] = $variation->id();
        }
        $this->entityTypeManager->getStorage('commerce_product')->create([
          'type' => self::TYPE,
          'title' => $name,
          'stores' => $store ? [$store] : [],
          'variations' => $variations,
          'status' => FALSE,
        ])->save();
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
    // Commits.
    unset($transaction);

    // Reloaded, so each knows which product it now belongs to.
    $variation_storage->resetCache();
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface[] $loaded */
    $loaded = $variation_storage->loadMultiple($ids);
    return array_map(fn($id) => $loaded[$id], $ids);
  }

  /**
   * The title of the product for a Square item.
   */
  protected function productName(array $item): string {
    return mb_substr(trim($item['name']), 0, 255) ?: 'Square item';
  }

  /**
   * The variation title for a Square item.
   */
  protected function variationTitle(array $item): string {
    $name = trim($item['variation_name']);
    // "Regular" is Square's name for an item's only variation.
    if (!in_array($name, ['', 'Regular', trim($item['name'])], TRUE)) {
      return mb_substr($name, 0, 255);
    }
    return ($item['unit'] ?? NULL) === ItemResolver::POUND ? 'Per lb' : 'Each';
  }

  /**
   * Builds a readable SKU that no variation uses yet.
   */
  protected function uniqueSku(string $text): string {
    $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($text));
    $base = trim(mb_substr($slug, 0, 200), '-') ?: 'square-item';
    $storage = $this->entityTypeManager->getStorage('commerce_product_variation');
    $sku = $base;
    for ($i = 2; $storage->getQuery()->accessCheck(FALSE)->condition('sku', $sku)->range(0, 1)->execute(); $i++) {
      $sku = "$base-$i";
    }
    return $sku;
  }

}
