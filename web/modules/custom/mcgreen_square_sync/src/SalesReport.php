<?php

namespace Drupal\mcgreen_square_sync;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Compares the stored Square sales with the card payments entered in Drupal.
 */
class SalesReport {

  /**
   * The payment gateway staff use to record cards processed in Square.
   */
  public const CARD_GATEWAY = 'square';

  /**
   * Staff have linked the sale to the Drupal order that records it.
   */
  public const LINKED = 'linked';

  /**
   * The sale's order was created from it.
   */
  public const IMPORTED = 'imported';

  /**
   * Staff have said the sale is not to be recorded.
   */
  public const IGNORED = 'ignored';

  /**
   * A suggested Drupal order covers the sale's card amount.
   */
  public const MATCHED = 'matched';

  /**
   * A card sale with no Drupal order to suggest.
   */
  public const MISSING = 'missing';

  /**
   * A cash sale, which is reconciled by hand.
   */
  public const CASH = 'cash';

  /**
   * Any other payment type, which needs a person to decide.
   */
  public const OTHER = 'other';

  public function __construct(
    protected SaleStorage $storage,
    protected DecisionStorage $decisions,
    protected SaleMatcher $matcher,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Builds the comparison from the configured start date onwards.
   *
   * @param \DateTimeImmutable $start
   *   Midnight, site time, on the first day to include.
   *
   * @return array
   *   An array with:
   *   - sales: Stored sales, newest first. Each has a "status" (one of this
   *     class's constants), "maps" (line mappings keyed by line UID), "link",
   *     "match" and "ignored_reason". "link" is NULL or an array of "order",
   *     "sales_total" (the cents of all sales linked to that order) and
   *     "order_total" (cents). "match" is NULL or an array of "order", "days"
   *     and "sale_ids".
   *   - unmatched_payments: Drupal card payments, newest first, that no Square
   *     sale accounts for. Each has "order", "time" and "amount".
   */
  public function build(\DateTimeImmutable $start): array {
    $settings = $this->configFactory->get('mcgreen_square_sync.settings');
    $window = (int) $settings->get('match_window_days');
    $sales = $this->storage->loadSince($start->getTimestamp());
    $links = $this->decisions->getLinks();
    $orders = $this->loadOrders(array_unique($links));
    $links = array_filter($links, fn(int $order_id) => isset($orders[$order_id]));
    $linked_orders = array_flip($links);

    // The sum of the sales linked to each order, including any older ones.
    $sales_totals = [];
    foreach ($this->storage->loadMultiple(array_keys($links)) as $sale_id => $sale) {
      $order_id = $links[$sale_id];
      $sales_totals[$order_id] = ($sales_totals[$order_id] ?? 0) + $sale['total'];
    }

    // An order may be entered some days before or after its sale. Orders that
    // already have a sale linked are settled, so they are never suggested.
    $payments = array_filter(
      $this->loadCardPayments($start->getTimestamp() - $window * 86400),
      fn(array $payment) => !isset($linked_orders[$payment['order']->id()]),
    );
    $ignored = $this->decisions->getIgnored();
    $imported = array_flip($this->decisions->getImportedSaleIds());
    $card_sales = [];
    foreach (array_diff_key($sales, $links, $ignored) as $sale_id => $sale) {
      if ($sale['card'] > 0) {
        $card_sales[$sale_id] = ['time' => $sale['sold_at'], 'amount' => $sale['card']];
      }
    }
    $matches = $this->matcher->match($card_sales, $payments, $window, $start->getTimezone());

    $maps = $this->decisions->getLineMaps();
    foreach ($sales as $sale_id => &$sale) {
      $sale['maps'] = $maps[$sale_id] ?? [];
      $sale['match'] = NULL;
      $sale['link'] = NULL;
      $sale['ignored_reason'] = $ignored[$sale_id] ?? NULL;
      if (isset($links[$sale_id])) {
        $order = $orders[$links[$sale_id]];
        $sale['status'] = isset($imported[$sale_id]) ? self::IMPORTED : self::LINKED;
        $sale['link'] = [
          'order' => $order,
          'sales_total' => $sales_totals[$order->id()],
          'order_total' => $this->toCents($order->getTotalPrice()?->getNumber()),
        ];
        continue;
      }
      $sale['status'] = match (TRUE) {
        isset($ignored[$sale_id]) => self::IGNORED,
        $sale['card'] > 0 => self::MISSING,
        $sale['cash'] > 0 && !$sale['other'] => self::CASH,
        default => self::OTHER,
      };
    }
    unset($sale);
    foreach ($matches as $payment_id => $match) {
      foreach ($match['sale_ids'] as $sale_id) {
        $sales[$sale_id]['status'] = self::MATCHED;
        $sales[$sale_id]['match'] = [
          'order' => $payments[$payment_id]['order'],
          'days' => $match['days'],
          'sale_ids' => $match['sale_ids'],
        ];
      }
    }

    $unmatched = array_filter(
      array_diff_key($payments, $matches),
      fn(array $payment) => $payment['time'] >= $start->getTimestamp(),
    );
    uasort($unmatched, fn(array $a, array $b) => $b['time'] <=> $a['time']);

    return ['sales' => $sales, 'unmatched_payments' => $unmatched];
  }

  /**
   * Works out the links to make for the suggestions staff selected.
   *
   * Selecting any sale of a combined suggestion links all of its sales, since
   * the suggestion only adds up as a whole.
   *
   * @param array $report
   *   The result of build().
   * @param string[] $selected
   *   Values of "sale ID:order ID", naming each suggestion as it was shown.
   *
   * @return int[]
   *   Order IDs keyed by sale ID. A selection that is no longer suggested is
   *   left out.
   */
  public function getSuggestedLinks(array $report, array $selected): array {
    $links = [];
    foreach ($selected as $value) {
      [$sale_id, $order_id] = explode(':', $value) + [1 => ''];
      $match = $report['sales'][$sale_id]['match'] ?? NULL;
      if ($match && (string) $match['order']->id() === $order_id) {
        $links += array_fill_keys($match['sale_ids'], (int) $order_id);
      }
    }
    return $links;
  }

  /**
   * Loads the orders that still exist.
   *
   * @param int[] $order_ids
   *   Order IDs.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface[]
   *   Orders keyed by ID.
   */
  protected function loadOrders(array $order_ids): array {
    return array_filter(
      $this->entityTypeManager->getStorage('commerce_order')->loadMultiple($order_ids),
      fn($order) => $order instanceof OrderInterface,
    );
  }

  /**
   * Loads the card payments entered in Drupal for orders placed since a time.
   *
   * @return array[]
   *   Keyed by payment ID, each with "order", "time" (when the order was
   *   placed, which staff can backdate) and "amount" in cents.
   */
  protected function loadCardPayments(int $since): array {
    $storage = $this->entityTypeManager->getStorage('commerce_payment');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('payment_gateway', self::CARD_GATEWAY)
      // A refunded payment was still a sale.
      ->condition('state', ['completed', 'partially_refunded', 'refunded'], 'IN')
      ->condition('order_id.entity.placed', $since, '>=')
      ->condition('order_id.entity.state', 'canceled', '<>')
      ->execute();

    $payments = [];
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    foreach ($storage->loadMultiple($ids) as $payment) {
      $order = $payment->getOrder();
      $payments[$payment->id()] = [
        'order' => $order,
        'time' => (int) $order->getPlacedTime(),
        'amount' => $this->toCents($payment->getAmount()->getNumber()),
      ];
    }
    return $payments;
  }

  /**
   * Converts a decimal amount to cents.
   */
  protected function toCents(?string $number): int {
    return (int) round((float) $number * 100);
  }

}
