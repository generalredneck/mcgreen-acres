<?php

namespace Drupal\crawler_rate_limit;

use RateLimit\Rate;

/**
 * An interface defining Crawler Rate Limit Backend factory classes.
 *
 * @package Drupal\crawler_rate_limit
 */
interface RateLimitBackendFactoryInterface {

  /**
   * Gets a Rate Limiter object based on the provided paremeters.
   *
   * @param string $backend
   *   Name of the Rate Limit backend.
   * @param \RateLimit\Rate $rate
   *   Rate object.
   * @param string $keyPrefix
   *   Rate Limiter key prefix.
   *
   * @return \RateLimit\RateLimiter
   *   The rate limiter object.
   */
  public function get(string $backend, Rate $rate, string $keyPrefix);

}
