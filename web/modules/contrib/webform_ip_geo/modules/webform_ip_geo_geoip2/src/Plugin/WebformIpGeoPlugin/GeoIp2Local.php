<?php

namespace Drupal\webform_ip_geo_geoip2\Plugin\WebformIpGeoPlugin;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Utility\Error;
use Drupal\webform_ip_geo\Plugin\WebformIpGeoPluginBase;
use GeoIp2\Database\Reader;

/**
 * Provider plugin backed by a local MaxMind GeoIP2 / GeoLite2 database.
 *
 * Unlike the ipapi.co provider this performs the lookup against an on-disk
 * MaxMind binary database (*.mmdb) via the geoip2/geoip2 library, so it is not
 * subject to any remote API rate limiting (HTTP 429 "Too Many Requests").
 *
 * The database path is read from this module's own configuration
 * (webform_ip_geo_geoip2.settings:database), set on the Webform IP Geo settings
 * form. There are no cross-module dependencies or fallbacks.
 *
 * @WebformIpGeoPlugin(
 *   id="geoip2",
 *   label="MaxMind GeoIP2 (local database)"
 * )
 */
class GeoIp2Local extends WebformIpGeoPluginBase {

  /**
   * {@inheritdoc}
   *
   * Not a URL: the base class hands whatever this returns straight to
   * makeProviderCall(), so it is used as the resolved database file path.
   */
  public function getProviderUrl() {
    return $this->resolveDatabasePath();
  }

  /**
   * {@inheritdoc}
   *
   * @noinspection PhpComposerExtensionStubsInspection
   */
  public function makeProviderCall($url, $ip) {
    if (empty($url) || !is_file($url) || !class_exists(Reader::class) || empty($ip)) {
      return [];
    }

    try {
      $reader = new Reader($url);
      $record = $reader->city($ip);
    }
    catch (\Throwable $exception) {
      // AddressNotFoundException (private/reserved IPs, common on local dev),
      // InvalidDatabaseException (missing/corrupt file) and friends must never
      // fatal a webform submission.
      DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '10.1.0',
        fn() => Error::logException(\Drupal::logger('webform_ip_geo'), $exception),
        fn() => watchdog_exception('webform_ip_geo', $exception));
      return [];
    }

    $subdivision = $record->mostSpecificSubdivision;
    return [
      'city' => $record->city->name ?? '',
      'region' => $subdivision->name ?? '',
      'region_code' => $subdivision->isoCode ?? '',
      'country_code' => $record->country->isoCode ?? '',
      // GeoLite2 does not carry the ISO-3166 alpha-3 code.
      'country_code_iso3' => '',
      'country_name' => $record->country->name ?? '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function mapProviderData(array $apiResponse) {
    return [
      '[city]' => $apiResponse['city'] ?? '',
      '[region]' => $apiResponse['region'] ?? '',
      '[region_code]' => $apiResponse['region_code'] ?? '',
      '[country_code]' => $apiResponse['country_code'] ?? '',
      '[country_code_iso3]' => $apiResponse['country_code_iso3'] ?? '',
      '[country_name]' => $apiResponse['country_name'] ?? '',
    ];
  }

  /**
   * Resolves the absolute path to the configured MaxMind City database file.
   *
   * @return string
   *   The resolved path. May not exist; callers must check.
   */
  protected function resolveDatabasePath() {
    $path = trim((string) \Drupal::config('webform_ip_geo_geoip2.settings')->get('database'));
    if ($path === '') {
      return '';
    }

    // Resolve paths relative to the Drupal root.
    if ($path[0] !== '/' && !preg_match('#^[a-zA-Z]:[\\\\/]#', $path)) {
      $path = DRUPAL_ROOT . '/' . $path;
    }

    $real = realpath($path);
    return $real !== FALSE ? $real : $path;
  }

}
