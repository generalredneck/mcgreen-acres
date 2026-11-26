<?php

namespace Drupal\Tests\mcgreen_square_sync\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\commerce_stock_local\Entity\StockLocation;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mcgreen_square_sync\DecisionStorage;
use Drupal\mcgreen_square_sync\ImportException;
use Drupal\mcgreen_square_sync\SaleImporter;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests creating orders from Square sales.
 *
 * @group mcgreen_square_sync
 * @covers \Drupal\mcgreen_square_sync\SaleImporter
 */
#[RunTestsInSeparateProcesses]
class SaleImporterTest extends SquareSyncKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_receipt_on_payment',
    'commerce_stock_field',
    'commerce_stock_local',
    'commerce_stock_local_test',
  ];

  /**
   * A counted product.
   */
  protected ProductVariation $eggs;

  /**
   * A by-weight package.
   */
  protected ProductVariation $chicken;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('commerce_payment');
    $this->installEntitySchema('commerce_order_report');
    $this->installEntitySchema('commerce_stock_location');
    $this->installConfig([
      'commerce_stock',
      'commerce_stock_local',
      'commerce_stock_local_test',
      'mcgreen_square_sync',
    ]);
    $this->installSchema('commerce_stock_local', [
      'commerce_stock_transaction',
      'commerce_stock_transaction_type',
      'commerce_stock_location_level',
    ]);
    $this->installSchema('mcgreen_square_sync', [
      'mcgreen_square_sale',
      'mcgreen_square_link',
      'mcgreen_square_line_map',
      'mcgreen_square_item_map',
      'mcgreen_square_ignored',
    ]);
    $this->config('system.date')->set('timezone.default', 'America/Chicago')->save();
    $this->config('system.mail')->set('interface.default', 'test_mail_collector')->save();
    $this->config('commerce_stock.service_manager')
      ->set('default_service_id', 'local_stock')
      ->set('stock_events_plugin_id', 'core_stock_events')
      ->save();
    // As on the site: stock moves when an order is placed, and neither editing
    // nor deleting an order moves it again.
    $this->config('commerce_stock.core_stock_events')
      ->set('core_stock_events_order_complete_event_type', 'placed')
      ->set('core_stock_events_order_updates', FALSE)
      ->set('core_stock_events_order_cancel', FALSE)
      ->save();
    StockLocation::create(['type' => 'default', 'name' => 'Farm', 'status' => 1])->save();
    foreach (['square', 'cash', 'zelle'] as $id) {
      PaymentGateway::create(['id' => $id, 'label' => ucfirst($id), 'plugin' => 'manual'])->save();
    }
    // As on the site: a receipt goes out when an order is paid.
    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_receipt_on_payment', 'send_receipt_on_paid', TRUE);
    $order_type->setReceiptBcc('farm@example.com');
    $order_type->save();

    OrderItemType::create([
      'id' => 'physical_product',
      'label' => 'Physical',
      'orderType' => 'default',
      'purchasableEntityType' => 'commerce_product_variation',
    ])->save();
    OrderItemType::create([
      'id' => 'by_weight',
      'label' => 'By weight',
      'orderType' => 'default',
      'purchasableEntityType' => 'commerce_product_variation',
    ])->save();
    ProductVariationType::create([
      'id' => 'by_weight',
      'label' => 'By weight',
      'orderItemType' => 'by_weight',
      'generateTitle' => FALSE,
    ])->save();
    $this->decimalField('commerce_product_variation', 'by_weight', 'field_max_weight');
    $this->decimalField('commerce_order_item', 'by_weight', 'field_packed_weight');

    $this->eggs = $this->variation('default', 'Dozen', []);
    $this->chicken = $this->variation('by_weight', 'Large', ['field_max_weight' => '5']);

    // Set directly, so a test can still swap what the importer is built from.
    $this->container->get('state')->set(SaleImporter::STOCK_SINCE_STATE, '2026-06-01');
  }

  /**
   * Adds a decimal field.
   */
  protected function decimalField(string $entity_type, string $bundle, string $name): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'type' => 'decimal',
    ])->save();
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ])->save();
  }

  /**
   * Creates a published product with one variation.
   */
  protected function variation(string $type, string $title, array $values): ProductVariation {
    $variation = ProductVariation::create($values + [
      'type' => $type,
      'sku' => $title,
      'title' => $title,
      'price' => new Price('99.00', 'USD'),
    ]);
    $variation->save();
    Product::create([
      'type' => 'default',
      'title' => $title . ' product',
      'stores' => [$this->store],
      'variations' => [$variation],
    ])->save();
    return $variation;
  }

  /**
   * A stored line.
   */
  protected function line(string $uid, ?string $catalog_id, string $quantity, int $total, array $extra = []): array {
    return $extra + [
      'uid' => $uid,
      'catalog_object_id' => $catalog_id,
      'item_type' => $catalog_id ? 'ITEM' : 'CUSTOM_AMOUNT',
      'name' => $catalog_id ?: 'Custom Amount',
      'variation_name' => '',
      'note' => '',
      'quantity' => $quantity,
      'unit' => NULL,
      'total' => $total,
    ];
  }

  /**
   * Stores a sale. Payments are given as [source type, cents].
   */
  protected function storeSale(array $items, array $payments, array $extra = [], string $id = 'sale'): void {
    $sale = $extra + [
      'sale_id' => $id,
      'sold_at' => strtotime('2026-07-04T15:04:00-05:00'),
      'currency' => 'USD',
      'total' => array_sum(array_column($payments, 1)),
      'card' => 0,
      'cash' => 0,
      'other' => 0,
      'refunded' => 0,
      'items' => $items,
      'payments' => [],
    ];
    foreach ($payments as $delta => [$type, $amount]) {
      $sale[strtolower($type)] += $amount;
      $sale['payments'][] = ['id' => "$id-pay$delta", 'source_type' => $type, 'amount' => $amount];
    }
    $this->container->get('mcgreen_square_sync.storage')->replace([$sale]);
  }

  /**
   * Maps the catalog item "EGGS" to the eggs variation.
   */
  protected function mapEggs(): void {
    $this->container->get('mcgreen_square_sync.decisions')
      ->setItemMap('EGGS', DecisionStorage::PRODUCT, (int) $this->eggs->id());
  }

  /**
   * Imports the stored sale.
   */
  protected function import(?string $gateway = NULL): Order {
    return $this->container->get('mcgreen_square_sync.importer')->import('sale', $gateway);
  }

  /**
   * The quantities of the sale transactions for a variation.
   */
  protected function stockSold(ProductVariation $variation): array {
    return array_map('floatval', $this->container->get('database')
      ->select('commerce_stock_transaction', 't')
      ->fields('t', ['qty'])
      ->condition('entity_id', $variation->id())
      ->execute()
      ->fetchCol());
  }

  /**
   * Counts the entities of a type.
   */
  protected function countEntities(string $entity_type): int {
    return (int) $this->container->get('entity_type.manager')->getStorage($entity_type)
      ->getQuery()->accessCheck(FALSE)->count()->execute();
  }

  /**
   * Every kind of line ends up on a completed, paid, reported order.
   */
  public function testImport(): void {
    $this->mapEggs();
    $decisions = $this->container->get('mcgreen_square_sync.decisions');
    $decisions->setItemMap('CHICKEN', DecisionStorage::BY_WEIGHT, (int) $this->chicken->getProductId());
    $decisions->setItemMap('BOKCHOY', DecisionStorage::NO_PRODUCT);
    $decisions->setLineMap('sale', 'tip', DecisionStorage::ADJUSTMENT);
    // 5.97 does not divide evenly by 0.66, but the line must still be exact.
    $this->storeSale([
      $this->line('eggs', 'EGGS', '2', 1999),
      $this->line('chicken', 'CHICKEN', '4.38', 4052, ['unit' => 'IMPERIAL_POUND']),
      $this->line('bok', 'BOKCHOY', '0.66', 597, ['unit' => 'IMPERIAL_POUND']),
      $this->line('tip', NULL, '1', 795, ['note' => 'tip']),
    ], [['CARD', 7543]]);

    $order = $this->import();
    // The report runs the end-of-request services in production.
    $order = Order::load($order->id());

    $this->assertSame('completed', $order->getState()->getId());
    $this->assertSame(strtotime('2026-07-04T15:04:00-05:00'), (int) $order->getPlacedTime());
    $this->assertNotEmpty($order->getOrderNumber());
    $this->assertSame('square', $order->get('payment_gateway')->target_id);
    $this->assertEquals(new Price('75.43', 'USD'), $order->getTotalPrice());
    $this->assertTrue($order->getBalance()->isZero());

    $items = $order->getItems();
    $this->assertSame(['physical_product', 'by_weight', 'square_item'], [
      $items[0]->bundle() === 'default' ? 'physical_product' : $items[0]->bundle(),
      $items[1]->bundle(),
      $items[2]->bundle(),
    ]);
    $this->assertEquals(new Price('19.99', 'USD'), $items[0]->getTotalPrice());
    $this->assertSame('4.38', $items[1]->get('field_packed_weight')->value);
    $this->assertStringEndsWith('(4.38 lb)', $items[1]->getTitle());
    $this->assertEquals(1, $items[1]->getQuantity());
    $this->assertSame('BOKCHOY', $items[2]->getTitle());
    $this->assertEquals(new Price('5.97', 'USD'), $items[2]->getTotalPrice());

    $adjustments = $order->getAdjustments();
    // The 1.00 not on any line shows up as its own adjustment.
    $this->assertSame(['tip', 'Square tip or other charge'], array_map(fn($a) => (string) $a->getLabel(), $adjustments));
    $this->assertEquals(new Price('1.00', 'USD'), $adjustments[1]->getAmount());

    $payments = $this->container->get('entity_type.manager')->getStorage('commerce_payment')->loadMultipleByOrder($order);
    $payment = reset($payments);
    $this->assertCount(1, $payments);
    $this->assertSame('completed', $payment->getState()->getId());
    $this->assertSame('sale-pay0', $payment->getRemoteId());
    $this->assertSame(strtotime('2026-07-04T15:04:00-05:00'), (int) $payment->getCompletedTime());

    $this->assertSame([-2.0], $this->stockSold($this->eggs));
    $this->assertSame([-1.0], $this->stockSold($this->chicken));

    $reports = $this->container->get('entity_type.manager')->getStorage('commerce_order_report')->loadMultiple();
    $types = array_count_values(array_map(fn($report) => $report->bundle(), $reports));
    $this->assertSame(1, $types['order_report']);
    $this->assertSame(3, $types['order_items_report']);

    $this->assertSame(['sale'], $decisions->getImportedSaleIds());
    $this->assertEmpty($this->container->get('state')->get('system.test_mail_collector', []));
  }

  /**
   * Proves the mail check above would notice a receipt.
   */
  public function testAnOrdinaryPaidOrderSendsMail(): void {
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'mail' => 'buyer@example.com',
      'state' => 'draft',
    ]);
    $order->save();
    $this->mapEggs();
    $item = $this->container->get('entity_type.manager')->getStorage('commerce_order_item')
      ->createFromPurchasableEntity($this->eggs);
    $item->save();
    $order->addItem($item);
    $order->save();
    Payment::create([
      'payment_gateway' => 'cash',
      'order_id' => $order->id(),
      'amount' => $order->getTotalPrice(),
      'state' => 'completed',
    ])->save();
    $this->container->get('commerce_payment.order_updater')->updateOrders();

    $this->assertNotEmpty($this->container->get('state')->get('system.test_mail_collector', []));
  }

  /**
   * A sale older than the stock date leaves stock alone.
   */
  public function testOldSaleKeepsStock(): void {
    $this->mapEggs();
    $this->storeSale(
      [$this->line('eggs', 'EGGS', '1', 1000)],
      [['CARD', 1000]],
      // The evening before, in site time, although already June 1 in UTC.
      ['sold_at' => strtotime('2026-05-31T23:00:00-05:00')],
    );
    $this->import();
    $this->assertSame([], $this->stockSold($this->eggs));
  }

  /**
   * Cash takes the chosen gateway, and a split sale makes two payments.
   */
  public function testCashAndSplitPayment(): void {
    $this->mapEggs();
    $this->storeSale([$this->line('eggs', 'EGGS', '3', 3000)], [['CARD', 1000], ['CASH', 2000]]);
    try {
      $this->import();
      $this->fail('A cash payment needs a gateway.');
    }
    catch (ImportException $e) {
      $this->assertStringContainsString('not by card', $e->getMessage());
    }
    $order = $this->import('zelle');

    $payments = $this->container->get('entity_type.manager')->getStorage('commerce_payment')->loadMultipleByOrder($order);
    $recorded = [];
    foreach ($payments as $payment) {
      $recorded[$payment->getPaymentGatewayId()] = $payment->getAmount()->getNumber();
    }
    $this->assertEquals(['square' => 10, 'zelle' => 20], $recorded);
    // The gateway that took the most.
    $this->assertSame('zelle', Order::load($order->id())->get('payment_gateway')->target_id);
  }

  /**
   * Sales that are not ready, or already recorded, create nothing.
   */
  public function testRefusals(): void {
    $decisions = $this->container->get('mcgreen_square_sync.decisions');
    $items = [$this->line('eggs', 'EGGS', '1', 1000)];
    $expect = function (string $message): void {
      $orders = $this->countEntities('commerce_order');
      try {
        $this->import('cash');
        $this->fail("Expected: $message");
      }
      catch (ImportException $e) {
        $this->assertStringContainsString($message, $e->getMessage());
      }
      $this->assertSame($orders, $this->countEntities('commerce_order'), $message);
    };

    $this->storeSale($items, [['CARD', 1000]]);
    $expect('1 line is not mapped');
    $this->mapEggs();

    $this->storeSale($items, [['CARD', 1000]], ['refunded' => 100]);
    $expect('refund');

    $this->storeSale($items, [['CARD', 1000]]);
    $decisions->ignore(['sale']);
    $expect('ignored');
    $decisions->restore(['sale']);

    $order = $this->import();
    $expect('already linked');

    // Without the link, the payment still gives it away.
    $this->container->get('database')->delete('mcgreen_square_link')->execute();
    $expect('already has this Square payment');

  }

  /**
   * Deleting an imported order takes it off the books and frees the sale.
   */
  public function testDeletingImportedOrder(): void {
    $this->mapEggs();
    $this->storeSale([$this->line('eggs', 'EGGS', '1', 1000)], [['CARD', 1000]]);
    $order = $this->import();
    // Another order, whose records must survive.
    $other = Order::create(['type' => 'default', 'store_id' => $this->store->id(), 'state' => 'completed']);
    $other->save();
    Payment::create([
      'payment_gateway' => 'cash',
      'order_id' => $other->id(),
      'amount' => new Price('5.00', 'USD'),
      'state' => 'completed',
    ])->save();

    $order->delete();
    // Runs at the end of the request in production.
    $this->container->get('commerce_payment.order_updater')->updateOrders();

    $this->assertSame(1, $this->countEntities('commerce_payment'));
    $this->assertSame(0, $this->countEntities('commerce_order_report'));
    $this->assertSame([], $this->container->get('mcgreen_square_sync.decisions')->getLinks());
    $this->assertNotEmpty($this->import()->id());
    // Stock is not put back, so both imports have taken from it.
    $this->assertSame([-1.0, -1.0], $this->stockSold($this->eggs));
  }

  /**
   * Deleting an order that was only linked removes nothing but the link.
   */
  public function testDeletingLinkedOrder(): void {
    $order = Order::create(['type' => 'default', 'store_id' => $this->store->id(), 'state' => 'completed']);
    $order->save();
    Payment::create([
      'payment_gateway' => 'square',
      'order_id' => $order->id(),
      'amount' => new Price('10.00', 'USD'),
      'state' => 'completed',
    ])->save();
    $decisions = $this->container->get('mcgreen_square_sync.decisions');
    $decisions->link(['sale' => (int) $order->id()]);

    $order->delete();

    $this->assertSame([], $decisions->getLinks());
    $this->assertSame(1, $this->countEntities('commerce_payment'));
  }

  /**
   * A failure part way through leaves nothing behind.
   */
  public function testFailureRollsBack(): void {
    $reports = $this->createMock('Drupal\commerce_reports\OrderReportGeneratorInterface');
    $reports->method('generateReports')->willThrowException(new \RuntimeException('Boom'));
    $this->container->set('commerce_reports.order_report_generator', $reports);
    $this->mapEggs();
    $this->storeSale([$this->line('eggs', 'EGGS', '1', 1000)], [['CARD', 1000]]);

    try {
      $this->import();
      $this->fail('No exception was thrown.');
    }
    catch (ImportException $e) {
      $this->assertStringContainsString('Boom', $e->getMessage());
    }
    foreach (['commerce_order', 'commerce_order_item', 'commerce_payment'] as $entity_type) {
      $this->assertSame(0, $this->countEntities($entity_type), $entity_type);
    }
    $this->assertSame([], $this->stockSold($this->eggs));
    $this->assertSame([], $this->container->get('mcgreen_square_sync.decisions')->getLinks());
  }

  /**
   * A custom amount counted as a package does not pretend it was weighed.
   */
  public function testCustomAmountAsPackage(): void {
    $this->container->get('mcgreen_square_sync.decisions')
      ->setLineMap('sale', 'c', DecisionStorage::PRODUCT, (int) $this->chicken->id(), '2');
    $this->storeSale([$this->line('c', NULL, '1', 8000)], [['CARD', 8000]]);

    $items = $this->import()->getItems();
    $this->assertSame('physical_product', $items[0]->bundle());
    $this->assertEquals(2, $items[0]->getQuantity());
    $this->assertEquals(new Price('80.00', 'USD'), $items[0]->getTotalPrice());
    $this->assertSame([-2.0], $this->stockSold($this->chicken));
  }

}
