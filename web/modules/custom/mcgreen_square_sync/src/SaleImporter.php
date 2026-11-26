<?php

namespace Drupal\mcgreen_square_sync;

use Drupal\commerce\Context;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_payment\PaymentOrderUpdaterInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_reports\OrderReportGeneratorInterface;
use Drupal\commerce_stock\StockServiceManagerInterface;
use Drupal\commerce_stock\StockTransactionsInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Creates Drupal orders from Square sales.
 *
 * The order is created already completed, so it never goes through the "place"
 * step of checkout. That step is what sends order emails, tracks conversions
 * and deducts stock, none of which should simply happen for a sale made in
 * person. What an imported order does need (a number, report entries and, for
 * recent sales, a stock deduction) is done here explicitly.
 */
class SaleImporter {

  use StringTranslationTrait;

  /**
   * State key holding the first sale date (Y-m-d) that deducts stock.
   */
  public const STOCK_SINCE_STATE = 'mcgreen_square_sync.stock_since';

  /**
   * Gateways a payment that was not a card can be recorded with.
   */
  public const OTHER_GATEWAYS = ['cash', 'zelle', 'check'];

  /**
   * Order item type for lines that have no product.
   */
  public const NO_PRODUCT_ITEM_TYPE = 'square_item';

  /**
   * Order data key naming the sale an order was imported from.
   */
  protected const ORDER_DATA_KEY = 'mcgreen_square_sale';

  /**
   * Order item field holding the weight of a by-weight package.
   */
  protected const WEIGHT_FIELD = 'field_packed_weight';

  public function __construct(
    protected SaleStorage $storage,
    protected DecisionStorage $decisions,
    protected ItemResolver $resolver,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StockServiceManagerInterface&StockTransactionsInterface $stockServiceManager,
    protected OrderReportGeneratorInterface $reportGenerator,
    protected PaymentOrderUpdaterInterface $paymentOrderUpdater,
    protected LockBackendInterface $lock,
    protected Connection $database,
    protected StateInterface $state,
    protected SaleFetcher $fetcher,
  ) {}

  /**
   * Why a sale cannot be imported yet.
   *
   * @param array $sale
   *   A stored sale.
   * @param array[] $line_maps
   *   The sale's line mappings, keyed by line UID.
   * @param array[] $item_maps
   *   Catalog item mappings, keyed by catalog object ID.
   *
   * @return string[]
   *   Reasons, or an empty list if the sale is ready.
   */
  public function getProblems(array $sale, array $line_maps, array $item_maps): array {
    $problems = [];
    if ($sale['refunded']) {
      $problems[] = (string) $this->t('it has a refund, so record it by hand');
    }
    $unmapped = 0;
    $no_size = 0;
    foreach ($sale['items'] as $item) {
      $type = $this->resolver->resolve($item, $line_maps, $item_maps)['type'];
      $unmapped += (int) ($type === ItemResolver::UNMAPPED);
      $no_size += (int) ($type === ItemResolver::NO_SIZE);
    }
    if ($unmapped) {
      $problems[] = (string) $this->formatPlural($unmapped, '1 line is not mapped', '@count lines are not mapped');
    }
    if ($no_size) {
      $problems[] = (string) $this->formatPlural($no_size, '1 line fits no package size', '@count lines fit no package size');
    }
    return $problems;
  }

  /**
   * Whether a sale has a payment whose gateway staff choose.
   */
  public function needsGatewayChoice(array $sale): bool {
    return $sale['cash'] > 0 || $sale['other'] > 0;
  }

  /**
   * The first sale date that deducts stock.
   */
  public function getStockSince(): string {
    $date = $this->state->get(self::STOCK_SINCE_STATE);
    if (!$date) {
      $date = $this->localDate(time());
      $this->setStockSince($date);
    }
    return $date;
  }

  /**
   * Sets the first sale date that deducts stock.
   */
  public function setStockSince(string $date): void {
    $this->state->set(self::STOCK_SINCE_STATE, $date);
  }

  /**
   * Whether importing a sale takes its items out of stock.
   */
  public function deductsStock(array $sale): bool {
    return $this->localDate($sale['sold_at']) >= $this->getStockSince();
  }

  /**
   * Imports a sale as a new completed order.
   *
   * @param string $sale_id
   *   The sale ID.
   * @param string|null $other_gateway
   *   The gateway for payments that were not by card, one of OTHER_GATEWAYS.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The new order.
   *
   * @throws \Drupal\mcgreen_square_sync\ImportException
   *   When nothing was imported.
   */
  public function import(string $sale_id, ?string $other_gateway = NULL): OrderInterface {
    // Stops a double click from creating two orders.
    $lock = 'mcgreen_square_sync.import.' . $sale_id;
    if (!$this->lock->acquire($lock)) {
      throw new ImportException((string) $this->t('This sale is already being imported.'));
    }
    try {
      $sale = $this->storage->loadMultiple([$sale_id])[$sale_id] ?? NULL;
      if (!$sale) {
        throw new ImportException((string) $this->t('There is no such sale.'));
      }
      $this->checkNotRecorded($sale);
      $line_maps = $this->decisions->getLineMaps()[$sale_id] ?? [];
      $item_maps = $this->decisions->getItemMaps();
      if ($problems = $this->getProblems($sale, $line_maps, $item_maps)) {
        throw new ImportException((string) $this->t('Not imported: @problems.', ['@problems' => implode('; ', $problems)]));
      }
      $payments = $this->planPayments($sale, $other_gateway);
      return $this->create($sale, $payments, $line_maps, $item_maps);
    }
    finally {
      $this->lock->release($lock);
    }
  }

  /**
   * Removes what an import left alongside an order that has been deleted.
   *
   * Commerce keeps a deleted order's payments and report entries. For an
   * imported order they would go on counting the sale, and block importing it
   * again. Stock is not put back.
   */
  public function removeImportRecords(OrderInterface $order): void {
    if (!$order->getData(self::ORDER_DATA_KEY)) {
      return;
    }
    foreach (['commerce_payment', 'commerce_order_report'] as $entity_type) {
      $storage = $this->entityTypeManager->getStorage($entity_type);
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('order_id', $order->id())
        ->execute();
      if ($ids) {
        $storage->delete($storage->loadMultiple($ids));
      }
    }
  }

  /**
   * Refuses a sale that Drupal already knows about.
   */
  protected function checkNotRecorded(array $sale): void {
    if (isset($this->decisions->getLinks()[$sale['sale_id']])) {
      throw new ImportException((string) $this->t('This sale is already linked to an order.'));
    }
    if (isset($this->decisions->getIgnored()[$sale['sale_id']])) {
      throw new ImportException((string) $this->t('This sale is ignored. Stop ignoring it first.'));
    }
    // A second guard, in case the link was removed but the payment remains.
    $ids = array_column($sale['payments'], 'id');
    if ($ids) {
      $storage = $this->entityTypeManager->getStorage('commerce_payment');
      $existing = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('remote_id', $ids, 'IN')
        ->range(0, 1)
        ->execute();
      if ($existing) {
        /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
        $payment = $storage->load(reset($existing));
        throw new ImportException((string) $this->t('Order @order already has this Square payment. Link the sale to it instead.', [
          '@order' => $payment->getOrder()?->getOrderNumber() ?? $payment->getOrderId(),
        ]));
      }
    }
  }

  /**
   * Chooses the gateway for each Square payment.
   *
   * @return array[]
   *   Each with "gateway", "amount" (cents) and "remote_id".
   */
  protected function planPayments(array $sale, ?string $other_gateway): array {
    $gateways = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    $plan = [];
    foreach ($sale['payments'] as $payment) {
      if ($payment['source_type'] === 'CARD') {
        $gateway = SalesReport::CARD_GATEWAY;
      }
      elseif (in_array($other_gateway, self::OTHER_GATEWAYS, TRUE)) {
        $gateway = $other_gateway;
      }
      else {
        throw new ImportException((string) $this->t('Choose how to record the payment that was not by card.'));
      }
      if (!$gateways->load($gateway)) {
        throw new ImportException((string) $this->t('There is no "@gateway" payment gateway.', ['@gateway' => $gateway]));
      }
      $plan[] = ['gateway' => $gateway, 'amount' => $payment['amount'], 'remote_id' => $payment['id']];
    }
    if (!$plan) {
      throw new ImportException((string) $this->t('The sale has no payments.'));
    }
    return $plan;
  }

  /**
   * Writes the order, its payments, stock and reports, all or nothing.
   */
  protected function create(array $sale, array $payments, array $line_maps, array $item_maps): OrderInterface {
    $transaction = $this->database->startTransaction();
    try {
      $order = $this->createOrder($sale, $payments, $line_maps, $item_maps);

      $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
      foreach ($payments as $payment) {
        $payment_storage->create([
          'payment_gateway' => $payment['gateway'],
          'order_id' => $order->id(),
          'amount' => $this->price($payment['amount'], $sale['currency']),
          'state' => 'completed',
          'remote_id' => $payment['remote_id'],
          'completed' => $sale['sold_at'],
        ])->save();
      }
      // Otherwise the amount paid is only updated at the end of the request.
      $this->paymentOrderUpdater->updateOrder($order, TRUE);

      if ($this->deductsStock($sale)) {
        $this->deductStock($order);
      }
      $this->reportGenerator->generateReports([$order->id()]);
      $this->decisions->recordImport($sale['sale_id'], (int) $order->id());
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      foreach (['commerce_order', 'commerce_order_item', 'commerce_payment'] as $entity_type) {
        $this->entityTypeManager->getStorage($entity_type)->resetCache();
      }
      throw $e instanceof ImportException ? $e : new ImportException((string) $this->t('Nothing was imported: @message', ['@message' => $e->getMessage()]), 0, $e);
    }
    // Commits.
    unset($transaction);
    return $order;
  }

  /**
   * Creates the order and its items.
   */
  protected function createOrder(array $sale, array $payments, array $line_maps, array $item_maps): OrderInterface {
    /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
    $store_storage = $this->entityTypeManager->getStorage('commerce_store');
    $store = $store_storage->loadDefault();
    if (!$store) {
      throw new ImportException((string) $this->t('There is no default store.'));
    }

    $items = [];
    $adjustments = [];
    $remaining = $sale['total'];
    foreach ($sale['items'] as $line) {
      $resolved = $this->resolver->resolve($line, $line_maps, $item_maps);
      $remaining -= $line['total'];
      if ($resolved['type'] === ItemResolver::ADJUSTMENT) {
        if ($line['total']) {
          $adjustments[] = $this->adjustment($line['note'] ?: $line['name'] ?: (string) $this->t('Adjustment'), $line['total'], $sale['currency']);
        }
        continue;
      }
      $item = $resolved['type'] === ItemResolver::VARIATION
        ? $this->productItem($resolved, $line, $sale['currency'])
        : $this->noProductItem($line, $sale['currency']);
      $item->save();
      $items[] = $item;
    }
    // Anything paid beyond the lines, such as a tip.
    if ($remaining) {
      $label = $remaining > 0 ? $this->t('Square tip or other charge') : $this->t('Square discount');
      $adjustments[] = $this->adjustment((string) $label, $remaining, $sale['currency']);
    }

    // The order is recorded under the gateway that took the most money.
    $largest = $payments;
    usort($largest, fn(array $a, array $b) => $b['amount'] <=> $a['amount']);

    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $order_storage->create([
      'type' => 'default',
      'store_id' => $store->id(),
      'uid' => 0,
      'state' => 'completed',
      'order_items' => $items,
      'payment_gateway' => $largest[0]['gateway'],
      'created' => $sale['sold_at'],
      'placed' => $sale['sold_at'],
      'completed' => $sale['sold_at'],
    ]);
    foreach ($adjustments as $adjustment) {
      $order->addAdjustment($adjustment);
    }
    // Marks the "order paid" event as already sent, so no receipt goes out.
    $order->setData('paid_event_dispatched', TRUE);
    $order->setData(self::ORDER_DATA_KEY, $sale['sale_id']);
    foreach (['field_needs_fulfillment', 'field_cover_stripe_fees'] as $field) {
      if ($order->hasField($field)) {
        $order->set($field, FALSE);
      }
    }
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $this->entityTypeManager->getStorage('commerce_order_type')->load($order->bundle());
    $pattern = $order_type->getNumberPattern();
    if ($pattern) {
      $order->setOrderNumber($pattern->getPlugin()->generate($order));
    }
    $order->save();
    if (!$pattern) {
      $order->setOrderNumber($order->id());
      $order->save();
    }

    $total = $order->getTotalPrice();
    $expected = $this->price($sale['total'], $sale['currency']);
    if (!$total || !$total->equals($expected)) {
      throw new ImportException((string) $this->t('Nothing was imported: the order would total @order, but Square took @sale.', [
        '@order' => (string) $total,
        '@sale' => (string) $expected,
      ]));
    }
    return $order;
  }

  /**
   * An order item for a line that is a product.
   */
  protected function productItem(array $resolved, array $line, string $currency): OrderItemInterface {
    $variation = $resolved['variation'];
    /** @var \Drupal\commerce_order\OrderItemStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('commerce_order_item');
    $item = $storage->createFromPurchasableEntity($variation);

    $weight = ($line['unit'] ?? NULL) === ItemResolver::POUND && $this->resolver->isByWeight($variation)
      ? $this->quantity($line['quantity'])
      : NULL;
    if ($item->hasField(self::WEIGHT_FIELD) && $weight === NULL) {
      // Without a weight, a by-weight item would sit on the "To weigh" list, so
      // record it as an ordinary product line.
      if ($this->entityTypeManager->getStorage('commerce_order_item_type')->load('physical_product')) {
        $item = $storage->createFromPurchasableEntity($variation, ['type' => 'physical_product']);
      }
    }

    $quantity = $this->quantity($resolved['quantity']);
    $item->setQuantity($quantity);
    // What Square charged, not today's catalog price.
    $item->setUnitPrice($this->price($line['total'], $currency)->divide($quantity), TRUE);
    if ($weight !== NULL && $item->hasField(self::WEIGHT_FIELD)) {
      $item->set(self::WEIGHT_FIELD, $weight);
      $item->setTitle($item->getTitle() . ' (' . $weight . ' lb)', TRUE);
    }
    return $item;
  }

  /**
   * An order item for a line with no product, under its Square name.
   */
  protected function noProductItem(array $line, string $currency): OrderItemInterface {
    $title = $line['name'] ?: (string) $this->t('Square item');
    if ($line['variation_name'] && !in_array($line['variation_name'], ['Regular', $line['name']], TRUE)) {
      $title .= ' (' . $line['variation_name'] . ')';
    }
    $quantity = $this->quantity($line['quantity']);
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $item */
    $item = $this->entityTypeManager->getStorage('commerce_order_item')->create([
      'type' => self::NO_PRODUCT_ITEM_TYPE,
      'title' => mb_substr($title, 0, 255),
      'quantity' => $quantity,
    ]);
    $item->setUnitPrice($this->price($line['total'], $currency)->divide($quantity), TRUE);
    return $item;
  }

  /**
   * Takes the order's products out of stock.
   */
  protected function deductStock(OrderInterface $order): void {
    $context = new Context($order->getCustomer(), $order->getStore());
    foreach ($order->getItems() as $item) {
      $variation = $item->getPurchasedEntity();
      if (!$variation) {
        continue;
      }
      $service = $this->stockServiceManager->getService($variation);
      if ($service->getStockChecker()->getIsAlwaysInStock($variation)) {
        continue;
      }
      $quantity = -1 * (float) $item->getQuantity();
      $location = $this->stockServiceManager->getTransactionLocation($context, $variation, $quantity);
      $this->stockServiceManager->sellStock($variation, $location->getId(), '', $quantity, NULL, NULL, $order->id(), $order->getCustomerId(), 'Square sale');
    }
  }

  /**
   * A fixed order-level amount.
   */
  protected function adjustment(string $label, int $cents, string $currency): Adjustment {
    return new Adjustment([
      'type' => 'custom',
      'label' => $label,
      'amount' => $this->price($cents, $currency),
      'locked' => TRUE,
    ]);
  }

  /**
   * An amount in cents as a price.
   */
  protected function price(int $cents, string $currency): Price {
    return new Price(sprintf('%.2f', $cents / 100), $currency);
  }

  /**
   * A quantity in the two decimals that order items store.
   */
  protected function quantity(string $quantity): string {
    return number_format(max((float) $quantity, 0.01), 2, '.', '');
  }

  /**
   * The date of a timestamp in site time.
   */
  protected function localDate(int $timestamp): string {
    return (new \DateTimeImmutable('@' . $timestamp))
      ->setTimezone($this->fetcher->getStartDate()->getTimezone())
      ->format('Y-m-d');
  }

}
