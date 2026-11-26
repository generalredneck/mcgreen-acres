<?php

namespace Drupal\mcgreen_square_sync;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Copies completed Square sales into local storage.
 *
 * A "sale" is a Square order together with its completed payments. Nothing is
 * written to Square, and no Drupal order is created or changed.
 */
class SaleFetcher {

  /**
   * State key holding the time of the last successful fetch.
   */
  public const LAST_FETCH_STATE = 'mcgreen_square_sync.last_fetch';

  public function __construct(
    protected SquareClient $client,
    protected SaleStorage $storage,
    protected ConfigFactoryInterface $configFactory,
    protected StateInterface $state,
    protected TimeInterface $time,
  ) {}

  /**
   * Fetches every sale since the configured start date.
   *
   * Everything is fetched each time, so later changes such as refunds are
   * picked up. Stored sales are only replaced once all requests have succeeded.
   *
   * @return int
   *   The number of sales fetched.
   *
   * @throws \Drupal\mcgreen_square_sync\SquareException
   */
  public function fetch(): int {
    $begin_time = $this->getStartDate()->format(\DateTimeInterface::RFC3339);

    $groups = [];
    foreach ($this->client->getLocationIds() as $location_id) {
      foreach ($this->client->getPayments($location_id, $begin_time) as $payment) {
        if (($payment['status'] ?? '') === 'COMPLETED') {
          $groups[$payment['order_id'] ?? $payment['id']][] = $payment;
        }
      }
    }

    $order_ids = [];
    foreach ($groups as $payments) {
      if (!empty($payments[0]['order_id'])) {
        $order_ids[] = $payments[0]['order_id'];
      }
    }
    $orders = array_column($this->client->getOrders($order_ids), NULL, 'id');

    $sales = [];
    foreach ($groups as $sale_id => $payments) {
      $sales[] = $this->buildSale((string) $sale_id, $payments, $orders[$sale_id] ?? []);
    }
    $this->storage->replace($sales);
    $this->state->set(self::LAST_FETCH_STATE, $this->time->getRequestTime());
    return count($sales);
  }

  /**
   * Returns midnight, site time, on the configured start date.
   */
  public function getStartDate(): \DateTimeImmutable {
    $timezone = $this->configFactory->get('system.date')->get('timezone.default') ?: 'UTC';
    return new \DateTimeImmutable(
      $this->configFactory->get('mcgreen_square_sync.settings')->get('start_date') . ' 00:00:00',
      new \DateTimeZone($timezone),
    );
  }

  /**
   * Builds a stored sale from Square payment and order objects.
   */
  protected function buildSale(string $sale_id, array $payments, array $order): array {
    $sale = [
      'sale_id' => $sale_id,
      'sold_at' => min(array_map(fn(array $p) => strtotime($p['created_at']), $payments)),
      'currency' => $payments[0]['total_money']['currency'] ?? 'USD',
      'total' => 0,
      'card' => 0,
      'cash' => 0,
      'other' => 0,
      'refunded' => 0,
      'items' => [],
      'payments' => [],
    ];

    foreach ($payments as $payment) {
      $type = $payment['source_type'] ?? '';
      // Includes any tip, so it is what the customer actually paid.
      $amount = $payment['total_money']['amount'] ?? 0;
      $refunded = $payment['refunded_money']['amount'] ?? 0;
      $column = match ($type) {
        'CARD' => 'card',
        'CASH' => 'cash',
        default => 'other',
      };
      $sale[$column] += $amount;
      $sale['total'] += $amount;
      $sale['refunded'] += $refunded;
      $sale['payments'][] = [
        'id' => $payment['id'],
        'source_type' => $type,
        'external_type' => $payment['external_details']['type'] ?? NULL,
        'amount' => $amount,
        'refunded' => $refunded,
      ];
    }

    foreach ($order['line_items'] ?? [] as $index => $line) {
      $sale['items'][] = [
        // Identifies the line when staff map it.
        'uid' => $line['uid'] ?? 'line-' . $index,
        'catalog_object_id' => $line['catalog_object_id'] ?? NULL,
        'item_type' => $line['item_type'] ?? NULL,
        'name' => $line['name'] ?? '',
        'variation_name' => $line['variation_name'] ?? '',
        'note' => $line['note'] ?? '',
        'quantity' => $line['quantity'] ?? '1',
        // Set for goods sold by weight, such as IMPERIAL_POUND.
        'unit' => $line['quantity_unit']['measurement_unit']['weight_unit'] ?? NULL,
        'total' => $line['total_money']['amount'] ?? 0,
      ];
    }
    return $sale;
  }

}
