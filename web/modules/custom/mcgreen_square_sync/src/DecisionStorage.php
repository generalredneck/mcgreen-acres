<?php

namespace Drupal\mcgreen_square_sync;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * Stores what staff have decided about Square sales.
 */
class DecisionStorage {

  /**
   * A line or catalog item that stands for a product variation.
   */
  public const PRODUCT = 'product';

  /**
   * A line that is money only, such as an overpayment.
   */
  public const ADJUSTMENT = 'adjustment';

  /**
   * A weighed item, recorded as the package size its weight falls in.
   */
  public const BY_WEIGHT = 'by_weight';

  /**
   * An item with no Drupal product, recorded under its Square name.
   */
  public const NO_PRODUCT = 'none';

  /**
   * Record the quantity sold in Square.
   */
  public const SQUARE_QUANTITY = 'square';

  /**
   * Record one unit for each line, whatever quantity Square has.
   */
  public const ONE_PER_LINE = 'line';

  protected const LINK_TABLE = 'mcgreen_square_link';

  protected const ITEM_TABLE = 'mcgreen_square_item_map';

  protected const MAP_TABLE = 'mcgreen_square_line_map';

  public function __construct(
    protected Connection $database,
    protected AccountInterface $currentUser,
    protected TimeInterface $time,
  ) {}

  /**
   * Records that Drupal orders cover sales.
   *
   * @param int[] $links
   *   Order IDs keyed by sale ID.
   */
  public function link(array $links): void {
    foreach ($links as $sale_id => $order_id) {
      $this->database->merge(self::LINK_TABLE)
        ->key('sale_id', (string) $sale_id)
        ->fields([
          'order_id' => $order_id,
          'uid' => $this->currentUser->id(),
          'linked' => $this->time->getRequestTime(),
        ])
        ->execute();
    }
  }

  /**
   * Removes the links of the given sales.
   *
   * @param string[] $sale_ids
   *   Sale IDs.
   */
  public function unlink(array $sale_ids): void {
    if ($sale_ids) {
      $this->database->delete(self::LINK_TABLE)
        ->condition('sale_id', $sale_ids, 'IN')
        ->execute();
    }
  }

  /**
   * Returns every link.
   *
   * @return int[]
   *   Order IDs keyed by sale ID.
   */
  public function getLinks(): array {
    return array_map('intval', $this->database->select(self::LINK_TABLE, 'l')
      ->fields('l', ['sale_id', 'order_id'])
      ->execute()
      ->fetchAllKeyed());
  }

  /**
   * Sets how a line is to be recorded, or clears it.
   *
   * @param string $sale_id
   *   The sale ID.
   * @param string $line_uid
   *   The line item UID.
   * @param string|null $type
   *   PRODUCT, ADJUSTMENT, or NULL to clear the line.
   * @param int|null $variation_id
   *   The product variation, for PRODUCT.
   * @param string|null $quantity
   *   How many of the variation, for PRODUCT.
   */
  public function setLineMap(string $sale_id, string $line_uid, ?string $type, ?int $variation_id = NULL, ?string $quantity = NULL): void {
    if ($type === NULL) {
      $this->database->delete(self::MAP_TABLE)
        ->condition('sale_id', $sale_id)
        ->condition('line_uid', $line_uid)
        ->execute();
      return;
    }
    $this->database->merge(self::MAP_TABLE)
      ->keys(['sale_id' => $sale_id, 'line_uid' => $line_uid])
      ->fields([
        'type' => $type,
        'variation_id' => $type === self::PRODUCT ? $variation_id : NULL,
        'quantity' => $type === self::PRODUCT ? $quantity : NULL,
      ])
      ->execute();
  }

  /**
   * Returns every line mapping.
   *
   * @return array[]
   *   Keyed by sale ID and then line UID, each with "type", "variation_id" and
   *   "quantity".
   */
  public function getLineMaps(): array {
    $maps = [];
    foreach ($this->database->select(self::MAP_TABLE, 'm')->fields('m')->execute() as $row) {
      $maps[$row->sale_id][$row->line_uid] = [
        'type' => $row->type,
        'variation_id' => $row->variation_id === NULL ? NULL : (int) $row->variation_id,
        // Drops the padding zeros the database adds.
        'quantity' => $row->quantity === NULL ? NULL : (string) (float) $row->quantity,
      ];
    }
    return $maps;
  }

  /**
   * Sets how a catalog item is to be recorded, or clears it.
   *
   * @param string $catalog_object_id
   *   The Square catalog item variation ID.
   * @param string|null $type
   *   PRODUCT, BY_WEIGHT, NO_PRODUCT, or NULL to clear the item.
   * @param int|null $target_id
   *   The product variation for PRODUCT, or the product for BY_WEIGHT.
   * @param string|null $quantity_rule
   *   SQUARE_QUANTITY or ONE_PER_LINE, for PRODUCT.
   */
  public function setItemMap(string $catalog_object_id, ?string $type, ?int $target_id = NULL, ?string $quantity_rule = NULL): void {
    if ($type === NULL) {
      $this->database->delete(self::ITEM_TABLE)
        ->condition('catalog_object_id', $catalog_object_id)
        ->execute();
      return;
    }
    $this->database->merge(self::ITEM_TABLE)
      ->key('catalog_object_id', $catalog_object_id)
      ->fields([
        'type' => $type,
        'target_id' => $type === self::NO_PRODUCT ? NULL : $target_id,
        'quantity_rule' => $type === self::PRODUCT ? $quantity_rule ?? self::SQUARE_QUANTITY : NULL,
      ])
      ->execute();
  }

  /**
   * Returns every catalog item mapping.
   *
   * @return array[]
   *   Keyed by catalog object ID, each with "type", "target_id" and
   *   "quantity_rule".
   */
  public function getItemMaps(): array {
    $maps = [];
    foreach ($this->database->select(self::ITEM_TABLE, 'i')->fields('i')->execute() as $row) {
      $maps[$row->catalog_object_id] = [
        'type' => $row->type,
        'target_id' => $row->target_id === NULL ? NULL : (int) $row->target_id,
        'quantity_rule' => $row->quantity_rule,
      ];
    }
    return $maps;
  }

  /**
   * Forgets the links to a deleted order.
   */
  public function deleteOrder(int $order_id): void {
    $this->database->delete(self::LINK_TABLE)
      ->condition('order_id', $order_id)
      ->execute();
  }

  /**
   * Forgets the mappings to a deleted product variation.
   */
  public function deleteVariation(int $variation_id): void {
    $this->database->delete(self::MAP_TABLE)
      ->condition('variation_id', $variation_id)
      ->execute();
    $this->database->delete(self::ITEM_TABLE)
      ->condition('type', self::PRODUCT)
      ->condition('target_id', $variation_id)
      ->execute();
  }

  /**
   * Forgets the by-weight mappings to a deleted product.
   */
  public function deleteProduct(int $product_id): void {
    $this->database->delete(self::ITEM_TABLE)
      ->condition('type', self::BY_WEIGHT)
      ->condition('target_id', $product_id)
      ->execute();
  }

}
