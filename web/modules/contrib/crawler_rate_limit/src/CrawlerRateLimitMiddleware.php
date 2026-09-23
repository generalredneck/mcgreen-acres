<?php

namespace Drupal\crawler_rate_limit;

use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Crawler Rate Limit Middleware.
 */
class CrawlerRateLimitMiddleware implements HttpKernelInterface {

  /**
   * The decorated kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The rate limit manager.
   *
   * @var \Drupal\crawler_rate_limit\RateLimitManagerInterface
   */
  protected $manager;

  /**
   * Constructs a new CrawlerRateLimitMiddleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param \Drupal\crawler_rate_limit\RateLimitManagerInterface $manager
   *   The rate limit manager.
   */
  public function __construct(HttpKernelInterface $http_kernel, RateLimitManagerInterface $manager) {
    $this->httpKernel = $http_kernel;
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    if ($this->manager->block($request)) {
      return new Response(new FormattableMarkup('Blocked.', []), Response::HTTP_FORBIDDEN);
    }

    if ($this->manager->limit($request)) {
      $headers = ['Retry-After' => $this->manager->retryAfter()];
      return new Response(new FormattableMarkup('Too many requests.', []), Response::HTTP_TOO_MANY_REQUESTS, $headers);
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

}
