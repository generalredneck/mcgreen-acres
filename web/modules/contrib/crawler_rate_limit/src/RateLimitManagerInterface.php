<?php

namespace Drupal\crawler_rate_limit;

use Symfony\Component\HttpFoundation\Request;

/**
 * Provides an interface to RateLimitManager.
 *
 * @package Drupal\crawler_rate_limit
 */
interface RateLimitManagerInterface {

  /**
   * Checks whether request should be rate limited.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return bool
   *   TRUE if request should be rate limited; FALSE otherwise.
   */
  public function limit(Request $request);

  /**
   * Checks whether request should be blocked.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return bool
   *   TRUE if request should be blocked; FALSE otherwise.
   */
  public function block(Request $request);

  /**
   * Checks whether rate limiter is enabled.
   *
   * @return bool
   *   TRUE if rate limiter is enabled; FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Returns how long to wait before making new request.
   *
   * @return int
   *   Number of seconds indicating how long to wait before making new request.
   */
  public function retryAfter();

}
