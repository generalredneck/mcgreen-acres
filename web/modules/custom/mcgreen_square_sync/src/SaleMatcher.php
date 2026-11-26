<?php

namespace Drupal\mcgreen_square_sync;

/**
 * Suggests which Square card sales a manually entered payment stands for.
 *
 * A payment normally records one sale, but staff sometimes enter several sales
 * from one day as a single order. Suggestions are made from the most to the
 * least certain, and no sale or payment is used twice:
 * 1. One sale, same day, same amount.
 * 2. Several sales from the same day that add up to the amount.
 * 3. One sale, same amount, within the date window.
 * 4. Several sales from one day within the date window that add up.
 */
class SaleMatcher {

  /**
   * Matches payments to sales.
   *
   * @param array[] $sales
   *   Keyed by sale ID, each with "time" (timestamp) and "amount" (cents).
   * @param array[] $payments
   *   Keyed by payment ID, each with "time" (timestamp) and "amount" (cents).
   * @param int $window_days
   *   The most calendar days between a sale and its payment.
   * @param \DateTimeZone $timezone
   *   The timezone that decides which day something happened on.
   *
   * @return array[]
   *   Keyed by payment ID, each with "sale_ids" (the sales it covers) and
   *   "days" (the payment's date minus the sales' date).
   */
  public function match(array $sales, array $payments, int $window_days, \DateTimeZone $timezone): array {
    $add_day = fn(array $record) => $record + ['day' => $this->dayNumber($record['time'], $timezone)];
    $sales = array_map($add_day, $sales);
    $payments = array_map($add_day, $payments);
    uasort($payments, fn(array $a, array $b) => $a['time'] <=> $b['time']);

    $matches = [];
    foreach (array_unique([0, $window_days]) as $window) {
      $this->matchSingles($matches, $sales, $payments, $window);
      $this->matchGroups($matches, $sales, $payments, $window);
    }
    return $matches;
  }

  /**
   * Numbers the local calendar date of a timestamp, so dates can be subtracted.
   */
  protected function dayNumber(int $time, \DateTimeZone $timezone): int {
    $date = (new \DateTimeImmutable('@' . $time))->setTimezone($timezone)->format('Y-m-d');
    return intdiv((new \DateTimeImmutable($date . ' UTC'))->getTimestamp(), 86400);
  }

  /**
   * Pairs payments with one sale of the same amount, closest in time first.
   */
  protected function matchSingles(array &$matches, array &$sales, array &$payments, int $window): void {
    $pairs = [];
    foreach ($payments as $payment_id => $payment) {
      foreach ($sales as $sale_id => $sale) {
        $days = $payment['day'] - $sale['day'];
        if ($sale['amount'] === $payment['amount'] && abs($days) <= $window) {
          $pairs[] = [
            'payment_id' => $payment_id,
            'sale_id' => $sale_id,
            'days' => $days,
            'rank' => [abs($days), abs($payment['time'] - $sale['time'])],
          ];
        }
      }
    }
    usort($pairs, fn(array $a, array $b) => $a['rank'] <=> $b['rank']);

    foreach ($pairs as $pair) {
      if (isset($payments[$pair['payment_id']], $sales[$pair['sale_id']])) {
        $matches[$pair['payment_id']] = [
          'sale_ids' => [$pair['sale_id']],
          'days' => $pair['days'],
        ];
        unset($payments[$pair['payment_id']], $sales[$pair['sale_id']]);
      }
    }
  }

  /**
   * Matches payments to several sales from one day that add up exactly.
   */
  protected function matchGroups(array &$matches, array &$sales, array &$payments, int $window): void {
    // Nearest day first, preferring sales made before the order was entered.
    $offsets = [0];
    for ($i = 1; $i <= $window; $i++) {
      array_push($offsets, $i, -$i);
    }

    foreach ($offsets as $days) {
      foreach ($payments as $payment_id => $payment) {
        $amounts = [];
        foreach ($sales as $sale_id => $sale) {
          if ($sale['day'] === $payment['day'] - $days) {
            $amounts[$sale_id] = $sale['amount'];
          }
        }
        $sale_ids = $this->findSubset($amounts, $payment['amount']);
        if ($sale_ids) {
          $matches[$payment_id] = ['sale_ids' => $sale_ids, 'days' => $days];
          unset($payments[$payment_id]);
          $sales = array_diff_key($sales, array_flip($sale_ids));
        }
      }
    }
  }

  /**
   * Finds the fewest amounts, two or more, that add up to the target.
   *
   * @param int[] $amounts
   *   Amounts in cents keyed by sale ID.
   * @param int $target
   *   The total to reach.
   *
   * @return array|null
   *   The sale IDs, or NULL if no combination adds up.
   */
  protected function findSubset(array $amounts, int $target): ?array {
    $reachable = [0 => []];
    foreach ($amounts as $sale_id => $amount) {
      // A sale for the whole amount is a single match, not part of a group.
      if ($amount <= 0 || $amount >= $target) {
        continue;
      }
      // Iterates a copy, so each sale is used at most once.
      foreach ($reachable as $sum => $ids) {
        $next = $sum + $amount;
        if ($next <= $target && (!isset($reachable[$next]) || count($reachable[$next]) > count($ids) + 1)) {
          $reachable[$next] = [...$ids, $sale_id];
        }
      }
    }
    return $reachable[$target] ?? NULL;
  }

}
