<?php

namespace Drupal\mcgreen_square_sync;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * The read-only parts of the Square API this site uses.
 */
class SquareClient {

  /**
   * The Square API version the response parsing was written against.
   */
  public const API_VERSION = '2026-09-16';

  /**
   * API hosts by environment.
   */
  protected const HOSTS = [
    'production' => 'https://connect.squareup.com',
    'sandbox' => 'https://connect.squareupsandbox.com',
  ];

  /**
   * The most order IDs Square accepts in one batch request.
   */
  protected const ORDER_BATCH_SIZE = 100;

  public function __construct(
    protected ClientInterface $httpClient,
    protected KeyRepositoryInterface $keyRepository,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns the IDs of the account's locations.
   *
   * @return string[]
   *   Location IDs.
   *
   * @throws \Drupal\mcgreen_square_sync\SquareException
   */
  public function getLocationIds(): array {
    $response = $this->request('GET', '/v2/locations');
    return array_column($response['locations'] ?? [], 'id');
  }

  /**
   * Returns a location's payments created at or after a time.
   *
   * @param string $location_id
   *   The location ID.
   * @param string $begin_time
   *   An RFC 3339 date and time.
   *
   * @return array[]
   *   Square Payment objects.
   *
   * @throws \Drupal\mcgreen_square_sync\SquareException
   */
  public function getPayments(string $location_id, string $begin_time): array {
    $query = [
      'location_id' => $location_id,
      'begin_time' => $begin_time,
      'sort_order' => 'ASC',
      'limit' => 100,
    ];
    $payments = [];
    do {
      $response = $this->request('GET', '/v2/payments', ['query' => $query]);
      $payments = array_merge($payments, $response['payments'] ?? []);
      $query['cursor'] = $response['cursor'] ?? NULL;
    } while ($query['cursor']);
    return $payments;
  }

  /**
   * Returns the orders with the given IDs.
   *
   * @param string[] $order_ids
   *   Square order IDs.
   *
   * @return array[]
   *   Square Order objects.
   *
   * @throws \Drupal\mcgreen_square_sync\SquareException
   */
  public function getOrders(array $order_ids): array {
    $orders = [];
    foreach (array_chunk($order_ids, self::ORDER_BATCH_SIZE) as $chunk) {
      $response = $this->request('POST', '/v2/orders/batch-retrieve', [
        'json' => ['order_ids' => $chunk],
      ]);
      $orders = array_merge($orders, $response['orders'] ?? []);
    }
    return $orders;
  }

  /**
   * Sends a request and returns the decoded response.
   *
   * @throws \Drupal\mcgreen_square_sync\SquareException
   */
  protected function request(string $method, string $path, array $options = []): array {
    $settings = $this->configFactory->get('mcgreen_square_sync.settings');
    $key_id = (string) $settings->get('access_token_key');
    $token = trim((string) $this->keyRepository->getKey($key_id)?->getKeyValue());
    if ($token === '') {
      throw new SquareException(sprintf('No Square access token found. Check the "%s" key and the file it reads.', $key_id));
    }

    $options += [
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Square-Version' => self::API_VERSION,
        'Accept' => 'application/json',
      ],
      'timeout' => 30,
      'http_errors' => FALSE,
    ];
    $host = self::HOSTS[$settings->get('environment')] ?? self::HOSTS['production'];

    // The original exception is deliberately not chained: its stack trace holds
    // the request, including the access token, and could end up in the logs.
    try {
      $response = $this->httpClient->request($method, $host . $path, $options);
    }
    catch (GuzzleException $e) {
      throw new SquareException('Could not reach Square: ' . $e->getMessage());
    }

    $status = $response->getStatusCode();
    $data = json_decode((string) $response->getBody(), TRUE);
    if ($status !== 200 || !is_array($data)) {
      $errors = array_map(
        fn(array $error) => trim(($error['code'] ?? '') . ' ' . ($error['detail'] ?? '')),
        $data['errors'] ?? [],
      );
      throw new SquareException(sprintf('Square returned HTTP %d for %s. %s', $status, $path, implode('; ', $errors)));
    }
    return $data;
  }

}
