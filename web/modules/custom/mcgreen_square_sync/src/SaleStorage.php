<?php

namespace Drupal\mcgreen_square_sync;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * Stores the local copy of Square sales.
 */
class SaleStorage {

  protected const TABLE = 'mcgreen_square_sale';

  public function __construct(
    protected Connection $database,
  ) {}

  /**
   * Makes the stored sales match the given list.
   *
   * @param array[] $sales
   *   Sales keyed by column name, with "items" and "payments" as arrays.
   */
  public function replace(array $sales): void {
    $transaction = $this->database->startTransaction();
    try {
      foreach ($sales as $sale) {
        $sale['items'] = json_encode($sale['items'], JSON_THROW_ON_ERROR);
        $sale['payments'] = json_encode($sale['payments'], JSON_THROW_ON_ERROR);
        $this->database->merge(self::TABLE)
          ->key('sale_id', $sale['sale_id'])
          ->fields($sale)
          ->execute();
      }
      $delete = $this->database->delete(self::TABLE);
      if ($sales) {
        $delete->condition('sale_id', array_column($sales, 'sale_id'), 'NOT IN');
      }
      $delete->execute();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Returns the sales made at or after a time, newest first.
   *
   * @return array[]
   *   Sales keyed by sale ID.
   */
  public function loadSince(int $timestamp): array {
    $query = $this->database->select(self::TABLE, 's')
      ->condition('sold_at', $timestamp, '>=');
    return $this->load($query);
  }

  /**
   * Returns the stored sales with the given IDs.
   *
   * @param string[] $sale_ids
   *   Sale IDs.
   *
   * @return array[]
   *   Sales keyed by sale ID.
   */
  public function loadMultiple(array $sale_ids): array {
    if (!$sale_ids) {
      return [];
    }
    $query = $this->database->select(self::TABLE, 's')
      ->condition('sale_id', $sale_ids, 'IN');
    return $this->load($query);
  }

  /**
   * Runs a query on the sales table and decodes the rows.
   */
  protected function load(SelectInterface $query): array {
    $result = $query->fields('s')
      ->orderBy('sold_at', 'DESC')
      ->orderBy('sale_id')
      ->execute();

    $sales = [];
    foreach ($result as $row) {
      $sale = (array) $row;
      foreach (['sold_at', 'total', 'card', 'cash', 'other', 'refunded'] as $column) {
        $sale[$column] = (int) $sale[$column];
      }
      $sale['items'] = json_decode($sale['items'], TRUE);
      $sale['payments'] = json_decode($sale['payments'], TRUE);
      $sales[$sale['sale_id']] = $sale;
    }
    return $sales;
  }

}
