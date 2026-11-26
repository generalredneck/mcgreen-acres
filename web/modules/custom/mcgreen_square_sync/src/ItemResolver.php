<?php

namespace Drupal\mcgreen_square_sync;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Works out what a line of a Square sale will be recorded as.
 *
 * Catalog items follow the mapping set once for the item. Lines without a
 * catalog item, such as custom amounts, follow the mapping set for that line.
 */
class ItemResolver {

  /**
   * Nobody has said what this is yet.
   */
  public const UNMAPPED = 'unmapped';

  /**
   * A quantity of a product variation.
   */
  public const VARIATION = 'variation';

  /**
   * An item with no Drupal product.
   */
  public const NO_PRODUCT = 'none';

  /**
   * Money only.
   */
  public const ADJUSTMENT = 'adjustment';

  /**
   * A weighed item that no package size can hold.
   */
  public const NO_SIZE = 'no_size';

  /**
   * Square's name for pounds.
   */
  public const POUND = 'IMPERIAL_POUND';

  /**
   * The variation field holding the most a by-weight package may weigh.
   */
  public const MAX_WEIGHT_FIELD = 'field_max_weight';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Resolves one line.
   *
   * @param array $item
   *   A stored sale item.
   * @param array[] $line_maps
   *   The sale's line mappings, keyed by line UID.
   * @param array[] $item_maps
   *   Catalog item mappings, keyed by catalog object ID.
   *
   * @return array
   *   An array with "type" (one of this class's constants) and, for VARIATION,
   *   "variation" and "quantity".
   */
  public function resolve(array $item, array $line_maps, array $item_maps): array {
    if (empty($item['catalog_object_id'])) {
      $map = $line_maps[$item['uid'] ?? ''] ?? NULL;
      return match ($map['type'] ?? NULL) {
        DecisionStorage::ADJUSTMENT => ['type' => self::ADJUSTMENT],
        DecisionStorage::PRODUCT => $this->asVariation($map['variation_id'], $map['quantity']),
        default => ['type' => self::UNMAPPED],
      };
    }

    $map = $item_maps[$item['catalog_object_id']] ?? NULL;
    return match ($map['type'] ?? NULL) {
      DecisionStorage::NO_PRODUCT => ['type' => self::NO_PRODUCT],
      DecisionStorage::PRODUCT => $this->asVariation(
        $map['target_id'],
        $map['quantity_rule'] === DecisionStorage::ONE_PER_LINE ? '1' : $item['quantity'],
      ),
      DecisionStorage::BY_WEIGHT => $this->asSize($map['target_id'], $item),
      default => ['type' => self::UNMAPPED],
    };
  }

  /**
   * Resolves to a variation, if it still exists.
   */
  protected function asVariation(int $variation_id, string $quantity): array {
    $variation = $this->entityTypeManager->getStorage('commerce_product_variation')->load($variation_id);
    return $variation
      ? ['type' => self::VARIATION, 'variation' => $variation, 'quantity' => $quantity]
      : ['type' => self::UNMAPPED];
  }

  /**
   * Resolves a weighed line to one package of the smallest size that holds it.
   */
  protected function asSize(int $product_id, array $item): array {
    /** @var \Drupal\commerce_product\Entity\ProductInterface|null $product */
    $product = $this->entityTypeManager->getStorage('commerce_product')->load($product_id);
    if (!$product) {
      return ['type' => self::UNMAPPED];
    }
    if (($item['unit'] ?? NULL) !== self::POUND) {
      return ['type' => self::NO_SIZE];
    }
    $sizes = array_filter($product->getVariations(), $this->isByWeight(...));
    usort($sizes, fn($a, $b) => $this->maxWeight($a) <=> $this->maxWeight($b));
    foreach ($sizes as $variation) {
      if ((float) $item['quantity'] <= $this->maxWeight($variation)) {
        return ['type' => self::VARIATION, 'variation' => $variation, 'quantity' => '1'];
      }
    }
    return ['type' => self::NO_SIZE];
  }

  /**
   * Whether a variation is a package in a weight range.
   */
  public function isByWeight(ProductVariationInterface $variation): bool {
    return $variation->hasField(self::MAX_WEIGHT_FIELD) && !$variation->get(self::MAX_WEIGHT_FIELD)->isEmpty();
  }

  /**
   * The most a by-weight package may weigh, in pounds.
   */
  protected function maxWeight(ProductVariationInterface $variation): float {
    return (float) $variation->get(self::MAX_WEIGHT_FIELD)->value;
  }

}
