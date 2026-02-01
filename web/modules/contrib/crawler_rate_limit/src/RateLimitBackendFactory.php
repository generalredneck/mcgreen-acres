<?php

namespace Drupal\crawler_rate_limit;

use Drupal\memcache\Connection\MemcachedConnection;
use RateLimit\ApcuRateLimiter;
use RateLimit\Exception\CannotUseRateLimiter;
use RateLimit\MemcachedRateLimiter;
use RateLimit\PredisRateLimiter;
use RateLimit\Rate;
use RateLimit\RedisRateLimiter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Factory class for creation of Crawler Rate Limit backend objects.
 *
 * @package Drupal\crawler_rate_limit
 */
class RateLimitBackendFactory implements RateLimitBackendFactoryInterface {

  /**
   * Prefix for the backend keys.
   *
   * @var string
   */
  private string $keyPrefix;

  /**
   * Rate object. Represents number of operations in a time interval.
   *
   * @var \RateLimit\Rate
   */
  private Rate $rate;

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  private ContainerInterface $container;

  /**
   * Class constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   */
  public function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public function get($backend_name, $rate, $keyPrefix) {
    $this->rate = $rate;
    $this->keyPrefix = $keyPrefix;

    switch ($backend_name) {
      case 'redis':
        $backend = $this->getRedis();
        break;

      case 'apcu':
        $backend = $this->getApcu();
        break;

      case 'memcached':
        $backend = $this->getMemcached();
        break;

      default:
        throw new \UnexpectedValueException(sprintf('Unsupported Crawler Rate Limit backend "%s". Supported backends: redis, apcu, memcached', $backend_name));
    }

    return $backend;
  }

  /**
   * Returns Redis Rate Limiter.
   *
   * @return \RateLimit\RedisRateLimiter|\RateLimit\PredisRateLimiter
   *   Redis Rate Limiter.
   */
  protected function getRedis() {
    try {
      $factory = $this->container->get('redis.factory');
    }
    catch (ServiceNotFoundException $exception) {
      throw new \RuntimeException('Redis Drupal module must be installed in order to use Crawler Rate Limit with the "redis" backend.');
    }

    $redis = $factory->getClient();
    $client_name = $factory->getClientName();

    if ($client_name === 'PhpRedis') {
      $backend = new RedisRateLimiter($this->rate, $redis, $this->keyPrefix);
    }
    elseif ($client_name === 'Predis') {
      $backend = new PredisRateLimiter($this->rate, $redis, $this->keyPrefix);
    }
    else {
      throw new \UnexpectedValueException(sprintf('Unsupported Redis client interface "%s". Supported interfaces: PhpRedis, Predis', $client_name));
    }

    return $backend;
  }

  /**
   * Returns Memcached Rate Limiter.
   *
   * @return \RateLimit\MemcachedRateLimiter
   *   Memcached Rate Limiter.
   */
  protected function getMemcached() {
    if (!class_exists('Memcached')) {
      throw new \RuntimeException('Memcached Rate Limit backend requires "memcached" PECL extension.');
    }

    try {
      $settings = $this->container->get('memcache.settings');
      $connection = new MemcachedConnection($settings);
    }
    catch (ServiceNotFoundException $exception) {
      throw new \RuntimeException('Memcache Drupal module must be installed in order to use Crawler Rate Limit with the "memcached" backend.');
    }

    // If multiple memcache servers are defined use the first one.
    $servers = array_keys($settings->get('servers', ['127.0.0.1:11211' => 'default']));
    $connection->addServer($servers[0]);
    $memcached = $connection->getMemcache();
    // MemcachedRateLimiter requires OPT_BINARY_PROTOCOL to be set to TRUE.
    // @see https://www.php.net/manual/en/memcached.increment.php#111187
    $memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, TRUE);

    return new MemcachedRateLimiter($this->rate, $memcached, $this->keyPrefix);
  }

  /**
   * Returns APCu Rate Limiter.
   *
   * @return \RateLimit\ApcuRateLimiter
   *   APCu Rate Limiter.
   */
  protected function getApcu() {
    try {
      $backend = new ApcuRateLimiter($this->rate, $this->keyPrefix);
    }
    catch (CannotUseRateLimiter $exception) {
      throw new \RuntimeException('APCu PECL extension must be installed in order to use Crawler Rate Limit with the "apcu" backend.');
    }

    return $backend;
  }

}
