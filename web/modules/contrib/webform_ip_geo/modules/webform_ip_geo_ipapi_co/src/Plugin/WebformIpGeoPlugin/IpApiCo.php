<?php

namespace Drupal\webform_ip_geo_ipapi_co\Plugin\WebformIpGeoPlugin;

use Drupal\webform_ip_geo\Plugin\WebformIpGeoPluginBase;

/**
 * Provider plugin for the ipapi.co API.
 *
 * @WebformIpGeoPlugin(
 *   id="ipapi_co",
 *   label="ipapi.co"
 * )
 */
class IpApiCo extends WebformIpGeoPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getProviderUrl() {
    return 'https://ipapi.co/%ip/json/';
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

}
