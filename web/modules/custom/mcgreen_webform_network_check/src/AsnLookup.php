<?php

namespace Drupal\mcgreen_webform_network_check;

use Drupal\Core\Config\ConfigFactoryInterface;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Looks up the autonomous system (network) an IP address belongs to.
 *
 * The database path is the asn_database key of
 * mcgreen_webform_network_check.settings: absolute, or relative to the Drupal
 * root. To point several GeoIP-consuming modules at one file, override it in
 * settings.php:
 *
 * @code
 * $config['mcgreen_webform_network_check.settings']['asn_database'] = $app_root . '/../private/geoip2/GeoLite2-ASN.mmdb';
 * @endcode
 */
class AsnLookup {

  /**
   * The lazily opened database reader, or FALSE if it can't be opened.
   *
   * @var \GeoIp2\Database\Reader|false|null
   */
  protected $reader;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LoggerInterface $logger,
    protected string $appRoot,
  ) {}

  /**
   * Looks up an IP address.
   *
   * @param string $ip
   *   An IPv4 or IPv6 address.
   *
   * @return array{asn: int, org: string}|null
   *   The ASN and its organization name, or NULL when unknown (private
   *   address, not in the database, or no database available).
   */
  public function lookup(string $ip): ?array {
    $reader = $this->getReader();
    if (!$reader || $ip === '') {
      return NULL;
    }
    try {
      $record = $reader->asn($ip);
    }
    catch (AddressNotFoundException | \InvalidArgumentException) {
      return NULL;
    }
    catch (\Exception $e) {
      $this->logger->error('ASN lookup failed for @ip: @message', [
        '@ip' => $ip,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
    if ($record->autonomousSystemNumber === NULL) {
      return NULL;
    }
    return [
      'asn' => (int) $record->autonomousSystemNumber,
      'org' => (string) $record->autonomousSystemOrganization,
    ];
  }

  /**
   * Opens the ASN database once per request.
   */
  protected function getReader(): ?Reader {
    if ($this->reader === NULL) {
      $this->reader = FALSE;
      $path = $this->getDatabasePath();
      if ($path && is_readable($path)) {
        try {
          $this->reader = new Reader($path);
        }
        catch (\Exception $e) {
          $this->logger->error('Could not open ASN database @path: @message', [
            '@path' => $path,
            '@message' => $e->getMessage(),
          ]);
        }
      }
      else {
        $this->logger->warning('ASN database not found at @path; all submissions pass the network check.', [
          '@path' => $path ?: '(not configured)',
        ]);
      }
    }
    return $this->reader ?: NULL;
  }

  /**
   * Resolves the configured ASN database path.
   *
   * @return string|null
   *   An absolute path, or NULL when none is configured.
   */
  public function getDatabasePath(): ?string {
    $path = trim((string) $this->configFactory->get('mcgreen_webform_network_check.settings')->get('asn_database'));
    if ($path === '') {
      return NULL;
    }
    return str_starts_with($path, '/') ? $path : $this->appRoot . '/' . $path;
  }

}
