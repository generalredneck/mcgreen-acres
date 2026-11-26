<?php

namespace Drupal\Tests\mcgreen_square_sync\Kernel;

use Drupal\key\Entity\Key;
use Drupal\mcgreen_square_sync\SaleFetcher;
use Drupal\mcgreen_square_sync\SquareException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests copying Square sales into local storage.
 *
 * @group mcgreen_square_sync
 * @covers \Drupal\mcgreen_square_sync\SquareClient
 * @covers \Drupal\mcgreen_square_sync\SaleFetcher
 * @covers \Drupal\mcgreen_square_sync\SaleStorage
 */
#[RunTestsInSeparateProcesses]
class SaleFetcherTest extends SquareSyncKernelTestBase {

  /**
   * Requests sent to the fake Square API.
   */
  protected array $requests = [];

  /**
   * Queued fake responses.
   */
  protected MockHandler $responses;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['mcgreen_square_sync']);
    $this->installSchema('mcgreen_square_sync', ['mcgreen_square_sale']);
    $this->config('system.date')->set('timezone.default', 'America/Chicago')->save();

    Key::load('square_access_token')
      ->set('key_provider', 'config')
      ->set('key_provider_settings', ['key_value' => 'secret-test-token'])
      ->save();

    $this->responses = new MockHandler();
    $stack = HandlerStack::create($this->responses);
    $stack->push(Middleware::history($this->requests));
    $this->container->set('http_client', new Client(['handler' => $stack]));
  }

  /**
   * Queues a JSON response.
   */
  protected function respond(array $body, int $status = 200): void {
    $this->responses->append(new Response($status, [], json_encode($body)));
  }

  /**
   * A Square payment object.
   */
  protected function payment(string $id, string $order_id, string $type, int $cents, array $extra = []): array {
    return $extra + [
      'id' => $id,
      'order_id' => $order_id,
      'status' => 'COMPLETED',
      'source_type' => $type,
      'created_at' => '2026-02-16T21:36:00.000Z',
      'total_money' => ['amount' => $cents, 'currency' => 'USD'],
    ];
  }

  /**
   * The fetcher service.
   */
  protected function fetcher(): SaleFetcher {
    return $this->container->get('mcgreen_square_sync.fetcher');
  }

  /**
   * The stored sales.
   */
  protected function sales(): array {
    return $this->container->get('mcgreen_square_sync.storage')->loadSince(0);
  }

  /**
   * Payments are paged, grouped into sales and stored with their items.
   */
  public function testFetch(): void {
    $this->respond(['locations' => [['id' => 'LOC']]]);
    $this->respond([
      'payments' => [
        $this->payment('P1', 'O1', 'CARD', 3000),
        $this->payment('P2', 'O2', 'CARD', 999, ['status' => 'CANCELED']),
      ],
      'cursor' => 'next-page',
    ]);
    $this->respond([
      'payments' => [
        $this->payment('P3', 'O1', 'CASH', 500, ['refunded_money' => ['amount' => 100]]),
      ],
    ]);
    $this->respond([
      'orders' => [
        [
          'id' => 'O1',
          'line_items' => [
            [
              'catalog_object_id' => 'VAR1',
              'item_type' => 'ITEM',
              'name' => 'Eggs',
              'variation_name' => 'Dozen',
              'quantity' => '0.5',
              'quantity_unit' => ['measurement_unit' => ['weight_unit' => 'IMPERIAL_POUND']],
              'base_price_money' => ['amount' => 7000],
              'total_money' => ['amount' => 3500],
            ],
          ],
        ],
      ],
    ]);

    $this->assertSame(1, $this->fetcher()->fetch());

    $sale = $this->sales()['O1'];
    $this->assertSame(3500, $sale['total']);
    $this->assertSame(3000, $sale['card']);
    $this->assertSame(500, $sale['cash']);
    $this->assertSame(100, $sale['refunded']);
    $this->assertSame(strtotime('2026-02-16T21:36:00Z'), $sale['sold_at']);
    $this->assertSame(['P1', 'P3'], array_column($sale['payments'], 'id'));
    $this->assertSame('VAR1', $sale['items'][0]['catalog_object_id']);
    $this->assertSame('0.5', $sale['items'][0]['quantity']);
    $this->assertSame('IMPERIAL_POUND', $sale['items'][0]['unit']);
    $this->assertSame(7000, $sale['items'][0]['unit_price']);
    // Square sent no line UID here, so the position stands in.
    $this->assertSame('line-0', $sale['items'][0]['uid']);

    $first = $this->requests[1]['request'];
    $this->assertSame('connect.squareup.com', $first->getUri()->getHost());
    $this->assertSame('Bearer secret-test-token', $first->getHeaderLine('Authorization'));
    parse_str($first->getUri()->getQuery(), $query);
    $this->assertSame('2026-01-11T00:00:00-06:00', $query['begin_time']);
    $this->assertStringContainsString('cursor=next-page', $this->requests[2]['request']->getUri()->getQuery());
    $this->assertSame('{"order_ids":["O1"]}', (string) $this->requests[3]['request']->getBody());
    $this->assertNotNull($this->container->get('state')->get(SaleFetcher::LAST_FETCH_STATE));
  }

  /**
   * A later fetch updates sales and drops ones Square no longer reports.
   */
  public function testRefetch(): void {
    $this->respond(['locations' => [['id' => 'LOC']]]);
    $this->respond([
      'payments' => [
        $this->payment('P1', 'O1', 'CARD', 100),
        $this->payment('P2', 'O2', 'CARD', 200),
      ],
    ]);
    $this->respond(['orders' => []]);
    $this->fetcher()->fetch();

    $this->respond(['locations' => [['id' => 'LOC']]]);
    $this->respond([
      'payments' => [
        $this->payment('P2', 'O2', 'CARD', 200, ['refunded_money' => ['amount' => 200]]),
      ],
    ]);
    $this->respond(['orders' => []]);
    $this->fetcher()->fetch();

    $this->assertSame(['O2'], array_keys($this->sales()));
    $this->assertSame(200, $this->sales()['O2']['refunded']);
  }

  /**
   * A failed request keeps what is stored and never exposes the token.
   */
  public function testApiError(): void {
    $this->respond(['locations' => [['id' => 'LOC']]]);
    $this->respond(['payments' => [$this->payment('P1', 'O1', 'CARD', 100)]]);
    $this->respond(['orders' => []]);
    $this->fetcher()->fetch();

    $this->respond(['errors' => [['code' => 'UNAUTHORIZED', 'detail' => 'Nope']]], 401);
    try {
      $this->fetcher()->fetch();
      $this->fail('No exception was thrown.');
    }
    catch (SquareException $e) {
      $this->assertStringContainsString('HTTP 401', $e->getMessage());
      $this->assertStringContainsString('UNAUTHORIZED Nope', $e->getMessage());
      $this->assertStringNotContainsString('secret-test-token', $e->getMessage());
      $this->assertNull($e->getPrevious());
    }
    $this->assertSame(['O1'], array_keys($this->sales()));
  }

  /**
   * Without a token, nothing is sent to Square.
   */
  public function testMissingToken(): void {
    $this->config('mcgreen_square_sync.settings')->set('access_token_key', 'no_such_key')->save();
    try {
      $this->fetcher()->fetch();
      $this->fail('No exception was thrown.');
    }
    catch (SquareException $e) {
      $this->assertStringContainsString('no_such_key', $e->getMessage());
    }
    $this->assertSame([], $this->requests);
  }

}
