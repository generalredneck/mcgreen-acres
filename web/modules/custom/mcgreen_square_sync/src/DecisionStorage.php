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
   * A line that stands for a product variation.
   */
  public const PRODUCT = 'product';

  /**
   * A line that is money only, such as an overpayment.
   */
  public const ADJUSTMENT = 'adjustment';

  protected const LINK_TABLE = 'mcgreen_square_link';

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
  }

}
