<?php

namespace Drupal\webform_ip_geo\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Webform IP Geo Plugin plugins.
 */
interface WebformIpGeoPluginInterface extends PluginInspectionInterface {

  /**
   * Returns the API endpoint for this provider.
   *
   * If makeProviderCall() is not overridden, the string needs to contain "%ip"
   * to be replaced with the actual IP.
   * Example: "https://some.geo.api/%ip/json"
   *
   * @return string
   *   The API endpoint string.
   */
  public function getProviderUrl();

  /**
   * Calls the provider API to retrieve the IP's geo data.
   *
   * @param string $url
   *   The API endpoint for the provider.
   * @param string $ip
   *   The IP to check.
   *
   * @return array
   *   The ip's geo data or an empty array if nothing was found.
   */
  public function makeProviderCall($url, $ip);

  /**
   * Maps the provider's API data to be used within Webforms.
   *
   * @param array $apiResponse
   *   The response returned from the API.
   *
   * @return array
   *   The mapped data to be available for the webform submission.
   */
  public function mapProviderData(array $apiResponse);

  /**
   * Retrieves the geo info from the API provider and returns the mapped data.
   *
   * @param string $ip
   *   The IP to check.
   *
   * @return array
   *   The mapped data to be available for the webform submission.
   */
  public function getData($ip);

  /**
   * Returns a string of all available tokens.
   *
   * @return string
   *   Tokens seperated by comma.
   */
  public function getTokenList();

}
