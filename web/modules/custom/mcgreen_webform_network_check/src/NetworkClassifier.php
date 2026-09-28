<?php

namespace Drupal\mcgreen_webform_network_check;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Classifies an IP address as a home/mobile ISP, privacy relay, or hosting.
 *
 * Commercial VPNs exit through the same hosting providers bots are run from,
 * so "hosting" means "quarantine for review", never "reject". Privacy relays
 * (iCloud Private Relay, Cloudflare WARP) and corporate proxies are real
 * people and are checked first so a hosting keyword can never catch them.
 */
class NetworkClassifier {

  /**
   * A home or mobile ISP, or anything unknown.
   */
  const PASS = 'pass';

  /**
   * A privacy relay or corporate proxy: real people, treated like PASS.
   */
  const RELAY = 'relay';

  /**
   * A hosting / data-center network: quarantined.
   */
  const HOSTING = 'hosting';

  public function __construct(
    protected AsnLookup $asnLookup,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Classifies an IP address.
   *
   * @param string $ip
   *   The IP address.
   *
   * @return array{class: string, asn: int|null, org: string, reason: string}
   *   The classification (one of the class constants), the network's ASN and
   *   organization name when known, and a short human-readable reason.
   */
  public function classify(string $ip): array {
    $network = $this->asnLookup->lookup($ip);
    if (!$network) {
      return [
        'class' => static::PASS,
        'asn' => NULL,
        'org' => '',
        'reason' => 'network unknown',
      ];
    }

    $config = $this->configFactory->get('mcgreen_webform_network_check.settings');
    $result = $network + ['class' => static::PASS, 'reason' => 'not a listed network'];

    if (in_array($network['asn'], array_map('intval', $config->get('relay_asns') ?? []), TRUE)) {
      return ['class' => static::RELAY, 'reason' => 'relay ASN list'] + $result;
    }
    if (in_array($network['asn'], array_map('intval', $config->get('hosting_asns') ?? []), TRUE)) {
      return ['class' => static::HOSTING, 'reason' => 'hosting ASN list'] + $result;
    }
    foreach ($config->get('hosting_keywords') ?? [] as $keyword) {
      if ($keyword !== '' && stripos($network['org'], $keyword) !== FALSE) {
        return ['class' => static::HOSTING, 'reason' => "keyword \"$keyword\""] + $result;
      }
    }
    return $result;
  }

  /**
   * Formats a network for display, e.g. "AS55286 SERVER-MANIA".
   *
   * @param array $classification
   *   A result of ::classify().
   *
   * @return string
   *   The label, or "unknown network".
   */
  public static function networkLabel(array $classification): string {
    return $classification['asn'] ? trim('AS' . $classification['asn'] . ' ' . $classification['org']) : 'unknown network';
  }

}
