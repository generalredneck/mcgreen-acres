<?php

namespace Drupal\Tests\mcgreen_square_sync\Unit;

use Drupal\mcgreen_square_sync\SaleMatcher;
use Drupal\Tests\UnitTestCase;

/**
 * Tests match suggestions between Square sales and Drupal payments.
 *
 * @group mcgreen_square_sync
 * @covers \Drupal\mcgreen_square_sync\SaleMatcher
 */
class SaleMatcherTest extends UnitTestCase {

  /**
   * A record dated in site time.
   */
  protected function at(string $date, int $amount): array {
    return [
      'time' => (new \DateTimeImmutable($date, new \DateTimeZone('America/Chicago')))->getTimestamp(),
      'amount' => $amount,
    ];
  }

  /**
   * Runs the matcher with a five day window.
   */
  protected function match(array $sales, array $payments, int $window = 5): array {
    return (new SaleMatcher())->match($sales, $payments, $window, new \DateTimeZone('America/Chicago'));
  }

  /**
   * An order entered the evening of the sale matches it, even across UTC days.
   */
  public function testSameLocalDay(): void {
    $matches = $this->match(
      ['a' => $this->at('2026-05-20 09:51', 1200)],
      [1 => $this->at('2026-05-20 21:15', 1200)],
      0,
    );
    $this->assertSame([1 => ['sale_ids' => ['a'], 'days' => 0]], $matches);
  }

  /**
   * An order dated a few days late matches, but not outside the window.
   */
  public function testDateWindow(): void {
    $sales = ['a' => $this->at('2026-11-07 11:27', 1000)];
    $this->assertSame(
      [1 => ['sale_ids' => ['a'], 'days' => 3]],
      $this->match($sales, [1 => $this->at('2026-11-10 11:27', 1000)]),
    );
    $this->assertSame([], $this->match($sales, [1 => $this->at('2026-11-13 11:27', 1000)]));
  }

  /**
   * A different amount is never matched on its own.
   */
  public function testAmountMustBeExact(): void {
    $this->assertSame([], $this->match(
      ['a' => $this->at('2026-05-01 10:00', 1001)],
      [1 => $this->at('2026-05-01 10:00', 1000)],
    ));
  }

  /**
   * Each sale is used once, and the nearest date wins.
   */
  public function testRepeatedAmounts(): void {
    $matches = $this->match(
      [
        'old' => $this->at('2026-05-04 13:26', 1000),
        'new' => $this->at('2026-05-11 10:01', 1000),
        'spare' => $this->at('2026-04-27 10:13', 1000),
      ],
      [
        1 => $this->at('2026-04-27 18:38', 1000),
        2 => $this->at('2026-05-11 10:01', 1000),
      ],
    );
    $this->assertSame(['spare'], $matches[1]['sale_ids']);
    $this->assertSame(['new'], $matches[2]['sale_ids']);
  }

  /**
   * A day's sales entered as one order are matched together.
   */
  public function testCombinedSales(): void {
    $matches = $this->match(
      [
        'a' => $this->at('2026-03-28 10:59', 3000),
        'b' => $this->at('2026-03-28 12:17', 700),
        'c' => $this->at('2026-03-28 12:36', 700),
        'd' => $this->at('2026-03-28 13:17', 700),
        'e' => $this->at('2026-03-28 14:29', 2700),
      ],
      [1 => $this->at('2026-03-28 16:33', 7800)],
    );
    $this->assertEqualsCanonicalizing(['a', 'b', 'c', 'd', 'e'], $matches[1]['sale_ids']);
    $this->assertSame(0, $matches[1]['days']);
  }

  /**
   * Sales from different days are never added together.
   */
  public function testNoCombiningAcrossDays(): void {
    $this->assertSame([], $this->match(
      [
        'a' => $this->at('2026-05-03 10:00', 1000),
        'b' => $this->at('2026-05-04 10:00', 3000),
      ],
      [1 => $this->at('2026-05-04 12:00', 4000)],
    ));
  }

  /**
   * Same-day sales that add up are preferred over one older sale.
   */
  public function testSameDayCombinationBeatsOlderSingle(): void {
    $matches = $this->match(
      [
        'older' => $this->at('2026-05-01 10:00', 4000),
        'a' => $this->at('2026-05-04 13:26', 1000),
        'b' => $this->at('2026-05-04 13:28', 3000),
      ],
      [1 => $this->at('2026-05-04 09:52', 4000)],
    );
    $this->assertEqualsCanonicalizing(['a', 'b'], $matches[1]['sale_ids']);
  }

  /**
   * A combination entered some days later is still found.
   */
  public function testLateCombination(): void {
    $matches = $this->match(
      [
        'a' => $this->at('2026-05-04 13:26', 1000),
        'b' => $this->at('2026-05-04 13:28', 3000),
      ],
      [1 => $this->at('2026-05-06 09:00', 4000)],
    );
    $this->assertSame(2, $matches[1]['days']);
    $this->assertCount(2, $matches[1]['sale_ids']);
  }

}
