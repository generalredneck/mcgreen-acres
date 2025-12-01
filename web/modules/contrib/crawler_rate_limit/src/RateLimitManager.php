<?php

namespace Drupal\crawler_rate_limit;

use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PublicStream;
use GeoIp2\Database\Reader;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use RateLimit\Exception\LimitExceeded;
use RateLimit\Rate;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

/**
 * Rate Limit Manager.
 *
 * @package Drupal\crawler_rate_limit
 */
class RateLimitManager implements RateLimitManagerInterface {

  /**
   * Prefix for the backend (rate limiter) keys.
   */
  const CRAWLER_RATE_LIMIT_KEY_PREFIX = 'crawler_rate_limit:';

  /**
   * Indicates that ASN could not be determined.
   */
  const CRL_ASN_UNKNOWN = -1;

  /**
   * Flag indicating whether rate limiter is enabled or not.
   *
   * @var bool
   */
  private bool $enabled;

  /**
   * Rate Limit backend factory.
   *
   * @var \Drupal\crawler_rate_limit\RateLimitBackendFactory
   */
  private RateLimitBackendFactory $factory;

  /**
   * Rate Limit backend.
   *
   * @var string
   */
  private string $backend;

  /**
   * Flag indicating whether to limit bot/crawler traffic.
   *
   * @var bool
   */
  private bool $limitBots;

  /**
   * Interval in seconds for bot traffic.
   *
   * @var int
   */
  private int $intervalBots;

  /**
   * Number of requests that is allowed in the interval for bot traffic.
   *
   * @var int
   */
  private int $requestsBots;

  /**
   * Flag indicating whether to also limit regular traffic at the visitor-level.
   *
   * @var bool
   */
  private bool $limitRegular;

  /**
   * Interval in seconds for regular traffic, visitor-level.
   *
   * @var int
   */
  private int $intervalRegular;

  /**
   * Requests allowed in the interval for regular traffic, visitor-level.
   *
   * @var int
   */
  private int $requestsRegular;

  /**
   * Flag indicating whether to also limit regular traffic at the ASN-level.
   *
   * @var bool
   */
  private bool $limitRegularAsn;

  /**
   * Interval in seconds for regular traffic, ASN-level.
   *
   * @var int
   */
  private int $intervalRegularAsn;

  /**
   * Number of requests allowed in the interval for regular traffic, ASN-level.
   *
   * @var int
   */
  private int $requestsRegularAsn;

  /**
   * Path to the local ASN database.
   *
   * @var string
   */
  private string $asnDbPath;

  /**
   * Value to use in the Retry-After HTTP header if the request is blocked.
   *
   * @var int
   */
  private int $retryAfter;

  /**
   * CrawlerDetect. Detects bots/crawlers/spiders via the user agent.
   *
   * @var \Jaybizzle\CrawlerDetect\CrawlerDetect
   */
  private CrawlerDetect $crawlerDetect;

  /**
   * IP addresses or subnets which are allowed to bypass rate limiting.
   *
   * @var array
   */
  protected array $ipAddressAllowlist;

  /**
   * ASNs that should be blocked.
   *
   * @var array
   */
  protected array $asnBlocklist;

  /**
   * IP address of the client.
   *
   * @var string|null
   */
  protected ?string $clientIp;

  /**
   * ASN of the client's IP address.
   *
   * @var int
   */
  protected int $clientAsn;

  /**
   * Class constructor.
   *
   * @param \Jaybizzle\CrawlerDetect\CrawlerDetect $crawlerDetect
   *   CrawlerDetect object.
   * @param \Drupal\crawler_rate_limit\RateLimitBackendFactory $factory
   *   Rate Limit backend factory.
   */
  public function __construct(CrawlerDetect $crawlerDetect, RateLimitBackendFactory $factory) {
    $this->crawlerDetect = $crawlerDetect;
    $this->factory = $factory;

    $settings = self::getSettings();
    $this->backend = $settings['backend'];
    $this->enabled = $settings['enabled'];
    $this->intervalRegular = $settings['regular_traffic']['interval'];
    $this->requestsRegular = $settings['regular_traffic']['requests'];
    $this->intervalRegularAsn = $settings['regular_traffic_asn']['interval'];
    $this->requestsRegularAsn = $settings['regular_traffic_asn']['requests'];
    $this->asnDbPath = $settings['regular_traffic_asn']['database'];
    $this->intervalBots = $settings['bot_traffic']['interval'];
    $this->requestsBots = $settings['bot_traffic']['requests'];
    $this->limitBots = $settings['limit_bots'];
    $this->limitRegular = $settings['limit_regular'];
    $this->limitRegularAsn = $settings['limit_regular_asn'];

    $this->ipAddressAllowlist = $settings['ip_address_allowlist'];
    $this->asnBlocklist = $settings['asn_blocklist'];

    // Set retryAfter to higher interval value until we figure out which type of
    // request we are handling in limit() method.
    $this
      ->retryAfter = max($this->intervalBots, $this->intervalRegular, $this->intervalRegularAsn);
  }

  /**
   * Check whether the requests limit has been reached for a given identifier.
   *
   * @param string $identifier
   *   The identifier for the traffic source (eg. bot name / UA+IP / ASN).
   * @param int $requests
   *   The number of requests allowed within the interval.
   * @param int $interval
   *   The interval in seconds.
   *
   * @return bool
   *   Whether or not the requests limit has been reached for the given traffic
   *   identifier.
   */
  private function limitReached(string $identifier, int $requests, int $interval): bool {
    try {
      $rate = Rate::custom($requests, $interval);
      $rate_limiter = $this->factory->get($this->backend, $rate, self::CRAWLER_RATE_LIMIT_KEY_PREFIX);
    }
    catch (\Exception $exception) {
      // Prevent missing dependencies or invalid settings to cause fatal errors.
      // All these will be reported on the Status report page.
      return FALSE;
    }

    try {
      $rate_limiter->limit($identifier);
    }
    catch (LimitExceeded $exception) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function limit(Request $request) {
    if (!$this->isEnabled()) {
      return FALSE;
    }

    $this->clientIp ??= $request->getClientIp();

    // Check if the request is from an IP address which is on the allowlist.
    if (IpUtils::checkIp($this->clientIp, $this->ipAddressAllowlist)) {
      return FALSE;
    }

    // Bypass rate limiting for certain request paths.
    $pattern = $this->pathSearchPattern();
    if (preg_match($pattern, $request->getRequestUri())) {
      return FALSE;
    }

    // If crawler, enforce crawler limit.
    if ($this->limitBots && $this->crawlerDetect->isCrawler()) {
      $this->retryAfter = $this->intervalBots;
      $identifier = $this->crawlerDetect->getMatches();
      return $this
        ->limitReached($identifier, $this->requestsBots, $this->intervalBots);
    }

    // Enforce the visitor-level regular traffic limit, if configured.
    if ($this->limitRegular) {
      $this->retryAfter = $this->intervalRegular;
      // As identifier we use combination of IP address and User Agent string.
      // @todo Change hashing algorithm to "xxh3" once the support for PHP 7.4
      // is removed.
      $identifier = hash('crc32c', $this->clientIp . $request->headers->get('user-agent'));
      if ($this->limitReached($identifier, $this->requestsRegular, $this->intervalRegular)) {
        return TRUE;
      }
    }

    // Visitor-level regular traffic limit not reached.
    // Enforce the ASN-level regular traffic limit, if configured.
    if ($this->limitRegularAsn) {
      $this->retryAfter = $this->intervalRegularAsn;
      $this->clientAsn ??= $this->ipToAsn($this->clientIp);
      if ($this->clientAsn != self::CRL_ASN_UNKNOWN) {
        $identifier = 'asn-' . $this->clientAsn;
        return $this->limitReached($identifier, $this->requestsRegularAsn, $this->intervalRegularAsn);
      }
    }

    // Allow the request through.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function block(Request $request) {
    if (!$this->isEnabled() || empty($this->asnBlocklist)) {
      return FALSE;
    }

    $this->clientIp ??= $request->getClientIp();
    $this->clientAsn ??= $this->ipToAsn($this->clientIp);
    if (in_array($this->clientAsn, $this->asnBlocklist)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Find ASN for an IP address.
   *
   * Possible reasons why ASN couldn't be determined:
   * - PHP package geoip2/geoip2 is missing
   * - GeoIP2 (ASN) database file is missing, corrupt or invalid
   * - IP address has not been found in the GeoIP2 database
   * - IP address belongs to a private IP address range.
   *
   * @param string $ip
   *   IP address.
   *
   * @return int
   *   ASN. If ASN could not be determined returns a constant CRL_ASN_UNKNOWN.
   */
  protected function ipToAsn($ip) {
    $asn = self::CRL_ASN_UNKNOWN;

    if (empty($this->asnDbPath)) {
      return $asn;
    }

    if (@class_exists('GeoIp2\Database\Reader')) {
      try {
        $reader = new Reader($this->asnDbPath);
        $record = $reader->asn($this->clientIp);
        $asn = $record->autonomousSystemNumber;
      }
      catch (\Exception $exception) {
        // Prevent "address not found" and other exceptions from causing
        // fatal errors.
      }
    }

    return $asn;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return $this->enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function retryAfter() {
    return $this->retryAfter;
  }

  /**
   * Converts value to integer if possible. Otherwise returns 0.
   *
   * @param mixed $value
   *   Value to be converted.
   *
   * @return int
   *   Value converted to integer.
   */
  public static function toIntOrZero($value) {
    if (is_int($value)) {
      return $value;
    }

    if (ctype_digit($value)) {
      return (int) $value;
    }

    return 0;
  }

  /**
   * Returns Crawler Rate Limit settings.
   *
   * @return array
   *   Crawler Rate Limit settings.
   */
  public static function getSettings() {
    $settings = Settings::get('crawler_rate_limit.settings', []);

    // Detect if deprecated settings are still used.
    if (
      (isset($settings['interval']) && isset($settings['operations']))
      && (!isset($settings['bot_traffic']) && !isset($settings['backend']))
    ) {
      $settings['deprecated'] = TRUE;
      $settings['backend'] = 'redis';
      $settings['bot_traffic'] = [
        'interval' => $settings['interval'],
        'requests' => $settings['operations'],
      ];
      unset($settings['interval']);
      unset($settings['operations']);
    }

    // Provide default values that will allow Drupal to run without throwing any
    // Exceptions while keeping the rate limiter disabled.
    $defaults = [
      'backend' => '',
      'enabled' => FALSE,
      'bot_traffic' => [
        'interval' => 0,
        'requests' => 0,
      ],
      'regular_traffic' => [
        'interval' => 0,
        'requests' => 0,
      ],
      'regular_traffic_asn' => [
        'interval' => 0,
        'requests' => 0,
        'database' => '',
      ],
      'limit_bots' => FALSE,
      'limit_regular' => FALSE,
      'limit_regular_asn' => FALSE,
      'deprecated' => FALSE,
      'ip_address_allowlist' => [],
      'asn_blocklist' => [],
    ];
    $settings = array_replace_recursive($defaults, $settings);

    // If user-provided values for interval and requests can't be converted to
    // integer avoid throwing the exception in constructor by setting the values
    // to 0. These are still invalid values for Crawler Rate Limit but will
    // allow Drupal to bootstrap and user will be able to see the error reported
    // on the "Status report" page.
    $settings['bot_traffic']['interval'] = self::toIntOrZero($settings['bot_traffic']['interval']);
    $settings['bot_traffic']['requests'] = self::toIntOrZero($settings['bot_traffic']['requests']);
    $settings['regular_traffic']['interval'] = self::toIntOrZero($settings['regular_traffic']['interval']);
    $settings['regular_traffic']['requests'] = self::toIntOrZero($settings['regular_traffic']['requests']);
    $settings['regular_traffic_asn']['interval'] = self::toIntOrZero($settings['regular_traffic_asn']['interval']);
    $settings['regular_traffic_asn']['requests'] = self::toIntOrZero($settings['regular_traffic_asn']['requests']);

    // Disable the limiter if unsupported backend has been detected.
    if (!in_array($settings['backend'], ['redis', 'memcached', 'apcu'])) {
      $settings['enabled'] = FALSE;
    }

    // Determine if bot/crawler traffic should be limited or not.
    if (
      $settings['bot_traffic']['interval'] > 0 &&
      $settings['bot_traffic']['requests'] > 0
    ) {
      $settings['limit_bots'] = TRUE;
    }

    // Determine if visitor-level regular traffic should be limited or not.
    if (
      $settings['regular_traffic']['interval'] > 0 &&
      $settings['regular_traffic']['requests'] > 0
    ) {
      $settings['limit_regular'] = TRUE;
    }

    // Determine if ASN-level regular traffic should be limited or not.
    if (
      $settings['regular_traffic_asn']['interval'] > 0 &&
      $settings['regular_traffic_asn']['requests'] > 0
    ) {
      $settings['limit_regular_asn'] = TRUE;
    }

    return $settings;
  }

  /**
   * Generate search pattern with paths that should not be rate limited.
   *
   * Some page requests trigger additional request which should not be counted
   * towards the limit. Such requests are ignored.
   *
   * Image module: generate image derivatives of publicly available files.
   * - path: public://styles/ (e.g. /sites/default/files/styles)
   * Image module: generate image derivatives of private files.
   * - path: /system/files/styles/
   * System module: generate optimized CSS/JS asset files.
   * - Drupal 10.0 and lower: public://css and public://js
   * - Drupal 10.1 and higher: assets://css and assets://js
   * History module: mark a node as read by the current user.
   * - path: /history/{node}/read (e.g. /history/18/read)
   * Contextual Links module: render contextual links.
   * - path: /contextual/render
   * Media module: render an oEmbed resource.
   * - path: /media/oembed.
   *
   * @return string
   *   Regex search pattern.
   */
  protected function pathSearchPattern(): string {
    $public_stream = PublicStream::basePath();
    $stream_wrappers = "($public_stream)";

    if (class_exists('\Drupal\Core\StreamWrapper\AssetsStream')) {
      // phpcs:disable
      $assets_stream = \Drupal\Core\StreamWrapper\AssetsStream::basePath();
      // phpcs:enable
      if ($public_stream !== $assets_stream) {
        $stream_wrappers .= "|($assets_stream)";
      }
    }

    return "@$stream_wrappers|(/system/files/styles/)|(/history/\d+/read)|(/contextual/render)|(/media/oembed)|(/favicon.ico)|(/search_api_autocomplete)@";
  }

}
