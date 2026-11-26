<?php

namespace Drupal\Tests\mcgreen_square_sync\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\mcgreen_square_sync\DecisionStorage;
use Drupal\mcgreen_square_sync\SalesReport;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests comparing stored Square sales with Drupal payments.
 *
 * @group mcgreen_square_sync
 * @covers \Drupal\mcgreen_square_sync\SalesReport
 * @covers \Drupal\mcgreen_square_sync\DecisionStorage
 */
#[RunTestsInSeparateProcesses]
class SalesReportTest extends SquareSyncKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('commerce_payment');
    $this->installConfig(['mcgreen_square_sync']);
    $this->installSchema('mcgreen_square_sync', [
      'mcgreen_square_sale',
      'mcgreen_square_link',
      'mcgreen_square_line_map',
      'mcgreen_square_item_map',
      'mcgreen_square_ignored',
    ]);
    foreach (['square', 'cash'] as $id) {
      PaymentGateway::create(['id' => $id, 'label' => $id, 'plugin' => 'manual'])->save();
    }
  }

  /**
   * Creates an order with one payment and returns the payment ID.
   */
  protected function paidOrder(string $placed, string $amount, string $gateway = 'square', string $order_state = 'completed', string $payment_state = 'completed'): int {
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'state' => $order_state,
      'placed' => strtotime($placed),
    ]);
    $order->save();
    $payment = Payment::create([
      'payment_gateway' => $gateway,
      'order_id' => $order->id(),
      'amount' => new Price($amount, 'USD'),
      'state' => $payment_state,
    ]);
    $payment->save();
    return (int) $payment->id();
  }

  /**
   * A stored sale.
   */
  protected function sale(string $id, string $sold, int $card, int $cash = 0): array {
    return [
      'sale_id' => $id,
      'sold_at' => strtotime($sold),
      'currency' => 'USD',
      'total' => $card + $cash,
      'card' => $card,
      'cash' => $cash,
      'other' => 0,
      'refunded' => 0,
      'items' => [],
      'payments' => [],
    ];
  }

  /**
   * Only card sales are matched, and only to valid "square" payments.
   */
  public function testBuild(): void {
    $this->container->get('mcgreen_square_sync.storage')->replace([
      $this->sale('matched', '2026-02-16T12:00:00-06:00', 1000),
      $this->sale('cash', '2026-02-16T13:00:00-06:00', 0, 2000),
      $this->sale('wrong-gateway', '2026-02-16T14:00:00-06:00', 3000),
      $this->sale('canceled', '2026-02-16T15:00:00-06:00', 4000),
      $this->sale('voided', '2026-02-16T16:00:00-06:00', 5000),
      $this->sale('too-old', '2026-01-01T12:00:00-06:00', 6000),
    ]);
    $matched = $this->paidOrder('2026-02-16T18:00:00-06:00', '10.00');
    // A Drupal cash order is never suggested, even for the same amount.
    $this->paidOrder('2026-02-16T18:00:00-06:00', '20.00', 'cash');
    $this->paidOrder('2026-02-16T18:00:00-06:00', '30.00', 'cash');
    $this->paidOrder('2026-02-16T18:00:00-06:00', '40.00', 'square', 'canceled');
    $this->paidOrder('2026-02-16T18:00:00-06:00', '50.00', 'square', 'completed', 'voided');
    $unmatched = $this->paidOrder('2026-03-01T12:00:00-06:00', '99.00');
    // Placed before the start date, so not reported as unmatched.
    $this->paidOrder('2026-01-08T12:00:00-06:00', '77.00');

    $start = new \DateTimeImmutable('2026-01-11', new \DateTimeZone('America/Chicago'));
    $report = $this->container->get('mcgreen_square_sync.report')->build($start);

    $statuses = array_map(fn(array $sale) => $sale['status'], $report['sales']);
    ksort($statuses);
    $this->assertSame([
      'canceled' => SalesReport::MISSING,
      'cash' => SalesReport::CASH,
      'matched' => SalesReport::MATCHED,
      'voided' => SalesReport::MISSING,
      'wrong-gateway' => SalesReport::MISSING,
    ], $statuses);

    $match = $report['sales']['matched']['match'];
    $this->assertSame((int) Payment::load($matched)->getOrderId(), (int) $match['order']->id());
    $this->assertSame(0, $match['days']);
    $this->assertSame(['matched'], $match['sale_ids']);
    $this->assertNull($report['sales']['cash']['match']);
    $this->assertSame([$unmatched], array_keys($report['unmatched_payments']));
    $this->assertSame(9900, $report['unmatched_payments'][$unmatched]['amount']);
  }

  /**
   * The report from the usual start date.
   */
  protected function report(): array {
    $start = new \DateTimeImmutable('2026-01-11', new \DateTimeZone('America/Chicago'));
    return $this->container->get('mcgreen_square_sync.report')->build($start);
  }

  /**
   * Selecting one sale of a combined suggestion links all of them.
   */
  public function testLinkingCombinedSales(): void {
    $this->container->get('mcgreen_square_sync.storage')->replace([
      $this->sale('a', '2026-05-04T13:26:00-05:00', 1000),
      $this->sale('b', '2026-05-04T13:28:00-05:00', 3000),
    ]);
    $order_id = Payment::load($this->paidOrder('2026-05-04T18:00:00-05:00', '40.00'))->getOrderId();
    $this->setOrderTotal($order_id, '40.00');
    $service = $this->container->get('mcgreen_square_sync.report');

    $report = $this->report();
    $this->assertSame([], $service->getSuggestedLinks($report, ['a:999999', 'nonsense', 'a']));
    $links = $service->getSuggestedLinks($report, ['a:' . $order_id]);
    $this->assertEqualsCanonicalizing(['a', 'b'], array_keys($links));
    $this->container->get('mcgreen_square_sync.decisions')->link($links);

    $report = $this->report();
    foreach (['a', 'b'] as $sale_id) {
      $sale = $report['sales'][$sale_id];
      $this->assertSame(SalesReport::LINKED, $sale['status']);
      $this->assertNull($sale['match']);
      $this->assertSame(4000, $sale['link']['sales_total']);
      $this->assertSame(4000, $sale['link']['order_total']);
    }
    $this->assertSame([], $report['unmatched_payments']);
  }

  /**
   * A linked order is not suggested again, and differing totals are visible.
   */
  public function testPartialLink(): void {
    $this->container->get('mcgreen_square_sync.storage')->replace([
      $this->sale('cash', '2026-05-04T13:26:00-05:00', 0, 1000),
      $this->sale('card', '2026-05-04T13:28:00-05:00', 4000),
    ]);
    $order_id = Payment::load($this->paidOrder('2026-05-04T18:00:00-05:00', '40.00'))->getOrderId();
    $this->setOrderTotal($order_id, '40.00');
    // Cash sales can be linked by hand to any order.
    $this->container->get('mcgreen_square_sync.decisions')->link(['cash' => $order_id]);

    $report = $this->report();
    $this->assertSame(1000, $report['sales']['cash']['link']['sales_total']);
    $this->assertSame(4000, $report['sales']['cash']['link']['order_total']);
    $this->assertSame(SalesReport::MISSING, $report['sales']['card']['status']);
    $this->assertSame([], $report['unmatched_payments']);
  }

  /**
   * Links survive a fetch, and go when their order is deleted.
   */
  public function testLinkLifetime(): void {
    $storage = $this->container->get('mcgreen_square_sync.storage');
    $decisions = $this->container->get('mcgreen_square_sync.decisions');
    $storage->replace([$this->sale('a', '2026-05-04T13:26:00-05:00', 1000)]);
    $order_id = Payment::load($this->paidOrder('2026-05-04T18:00:00-05:00', '10.00'))->getOrderId();
    $decisions->link(['a' => $order_id]);

    $storage->replace([]);
    $storage->replace([$this->sale('a', '2026-05-04T13:26:00-05:00', 1000)]);
    $this->assertSame(SalesReport::LINKED, $this->report()['sales']['a']['status']);

    Order::load($order_id)->delete();
    $this->assertSame([], $decisions->getLinks());
    $this->assertSame(SalesReport::MISSING, $this->report()['sales']['a']['status']);
  }

  /**
   * Custom amounts can be mapped to a product or an adjustment, and cleared.
   */
  public function testLineMaps(): void {
    $this->container->get('mcgreen_square_sync.storage')->replace([
      $this->sale('a', '2026-05-04T13:26:00-05:00', 1000),
    ]);
    $decisions = $this->container->get('mcgreen_square_sync.decisions');
    $variation = ProductVariation::create(['type' => 'default', 'sku' => 'eggs', 'title' => 'Dozen']);
    $variation->save();

    $decisions->setLineMap('a', 'line1', DecisionStorage::PRODUCT, (int) $variation->id(), '2.5');
    $decisions->setLineMap('a', 'line2', DecisionStorage::ADJUSTMENT, (int) $variation->id(), '9');
    $this->assertSame([
      'line1' => ['type' => 'product', 'variation_id' => (int) $variation->id(), 'quantity' => '2.5'],
      'line2' => ['type' => 'adjustment', 'variation_id' => NULL, 'quantity' => NULL],
    ], $this->report()['sales']['a']['maps']);

    $decisions->setLineMap('a', 'line2', NULL);
    $this->assertSame(['line1'], array_keys($decisions->getLineMaps()['a']));

    $variation->delete();
    $this->assertSame([], $decisions->getLineMaps());
  }

  /**
   * Imported and ignored sales are settled, and each can be told apart.
   */
  public function testImportedAndIgnored(): void {
    $this->container->get('mcgreen_square_sync.storage')->replace([
      $this->sale('imported', '2026-05-04T13:26:00-05:00', 1000),
      $this->sale('ignored', '2026-05-04T13:28:00-05:00', 4000),
    ]);
    $decisions = $this->container->get('mcgreen_square_sync.decisions');
    $imported = Payment::load($this->paidOrder('2026-05-04T18:00:00-05:00', '10.00'))->getOrderId();
    $other = $this->paidOrder('2026-05-04T18:00:00-05:00', '40.00');
    $decisions->recordImport('imported', (int) $imported);
    $decisions->ignore(['ignored'], 'Test sale');

    $report = $this->report();
    $this->assertSame(SalesReport::IMPORTED, $report['sales']['imported']['status']);
    $this->assertSame(SalesReport::IGNORED, $report['sales']['ignored']['status']);
    $this->assertSame('Test sale', $report['sales']['ignored']['ignored_reason']);
    // An ignored sale accounts for no payment.
    $this->assertNull($report['sales']['ignored']['match']);
    $this->assertSame([$other], array_keys($report['unmatched_payments']));

    // Unlinking never frees an imported sale.
    $decisions->unlink(['imported']);
    $this->assertArrayHasKey('imported', $decisions->getLinks());

    // Linking an ignored sale stops ignoring it.
    $decisions->link(['ignored' => (int) Payment::load($other)->getOrderId()]);
    $this->assertSame([], $decisions->getIgnored());
    $this->assertSame(SalesReport::LINKED, $this->report()['sales']['ignored']['status']);
  }

  /**
   * Gives an order without items a total.
   */
  protected function setOrderTotal(string|int $order_id, string $amount): void {
    $order = Order::load($order_id);
    $order->addItem($this->orderItem($amount));
    $order->save();
  }

  /**
   * A one-off order item.
   */
  protected function orderItem(string $amount): OrderItem {
    $item = OrderItem::create([
      'type' => 'test',
      'title' => 'Item',
      'quantity' => 1,
      'unit_price' => new Price($amount, 'USD'),
    ]);
    $item->save();
    return $item;
  }

}
