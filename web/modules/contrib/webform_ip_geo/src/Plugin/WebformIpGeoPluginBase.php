<?php

namespace Drupal\webform_ip_geo\Plugin;

use Drupal\Component\Plugin\PluginBase;
use GuzzleHttp\Exception\RequestException;

/**
 * Base class for Webform IP Geo Plugin plugins.
 */
abstract class WebformIpGeoPluginBase extends PluginBase implements WebformIpGeoPluginInterface {

  /**
   * {@inheritdoc}
   *
   * @noinspection PhpComposerExtensionStubsInspection
   */
  public function makeProviderCall($url, $ip) {
    $uri = strtr($url, ['%ip' => $ip]);

    $client = \Drupal::httpClient();
    try {
      $request = $client->get($uri);

      return json_decode($request->getBody()->getContents(), TRUE);
    }
    catch (RequestException $exception) {
      watchdog_exception('webform_ip_geo', $exception);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getData($ip) {
    $url = $this->getProviderUrl();
    $data = $this->makeProviderCall($url, $ip);
    return $this->mapProviderData($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenList() {
    $mapping = $this->mapProviderData([]);
    $tokens = array_keys($mapping);
    return implode(', ', $tokens);
  }

}
