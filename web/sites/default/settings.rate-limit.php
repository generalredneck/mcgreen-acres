<?php

/**
 * Below configuration uses a redis backend and will limit each
 * crawler / bot (identified by User-Agent string) to a maximum of 100
 * requests every 600 seconds.
 *
 * Regular traffic (human visitors and bots not openly identifying as bots)
 * will be limited to a maximum of 300 requests per visitor
 * (identified by IP address + User-Agent string) every 600 seconds.
 *
 * Regular traffic will additionally be limited at the ASN-level to a
 * maximum of 600 requests per ASN every 600 seconds.
 *
 * @see https://en.wikipedia.org/wiki/Autonomous_system_(Internet)
 */

/**
 * Enable or disable rate limiting. Required.
 *
 * If set to FALSE, all the module's functionality will be entirely disabled
 * regardless of all the other settings below.
 */
$settings['crawler_rate_limit.settings']['enabled'] = TRUE;

/**
 * Define which backend to use. Required.
 *
 * Supported and properly configured backend is necessary for normal
 * operation of the module. If backend is not set, all the module's
 * functionality will be disabled.
 *
 * Supported backends: redis, memcached, apcu.
 */
$settings['crawler_rate_limit.settings']['backend'] = 'apcu';


/**
 * Limit for crawler / bot traffic (visitors that openly identify as
 * crawlers / bots). Optional. Omit to disable.
 *
 * Note: If this section is omitted (undefined), bot traffic will be treated
 * in the same way as regular traffic.
 */
$settings['crawler_rate_limit.settings']['bot_traffic'] = [
  // Time interval in seconds. Must be whole number greater than zero.
  'interval' => 600,
  // Number of requests allowed in the given time interval per crawler or
  // bot (identified by User-Agent string). Must be a whole number greater
  // than zero.
  'requests' => 100,
];

/**
 * Limits for regular website traffic (visitors that don't openly identify
 * as crawlers / bots). Optional. Omit to disable.
 *
 * Visitor-level (IP address + User-Agent string) regular traffic rate
 * limit.
 */
$settings['crawler_rate_limit.settings']['regular_traffic'] = [
  // Time interval in seconds. Must be whole number greater than zero.
  'interval' => 600,
  // Number of requests allowed in the given time interval per regular
  // visitor (identified by combination of IP address + User-Agent string).
  'requests' => 300,
];

/**
 * Autonomous system-level (ASN) regular traffic rate limit. Optional. Omit
 * to disable.
 *
 * Useful if the following two conditions are met:
 *   1. Unwanted traffic is coming from a large number of different IP
 *      addresses and rate limiting based on the IP address and User Agent
 *      is not effective.
 *   2. Small number of distinct ASNs are identified as origin of this
 *      traffic (by obtaining ASN numbers for each of the unwanted IP
 *      addresses).
 *
 * Requires geoip2/geoip2 package and associated ASN Database.
 *
 * @see https://github.com/maxmind/GeoIP2-php
 * @see https://dev.maxmind.com/geoip/docs/databases/asn#binary-database
 */
$settings['crawler_rate_limit.settings']['regular_traffic_asn'] = [
  // Time interval in seconds. Must be whole number greater than zero.
  'interval' => 600,
  // Number of requests allowed in the given time interval per autonomous
  // system number (ASN).
  'requests' => 600,
  // Path to the local ASN Database file. Must be an up-to-date,
  // GeoLite2/GeoIP2 binary ASN Database. Consider updating automatically
  // via GeoIP Update or cron.
  // @see https://dev.maxmind.com/geoip/updating-databases
  // Note that the database path is also required by ASN blocking feature.
  'database' => $app_root . '/../private/geoip2/GeoLite2-ASN.mmdb',
];

/**
 * Allow specified IP addresses to bypass rate limiting. Optional.
 *
 * Useful if your website is maintained by a number of users all accessing
 * the site from the same location, using the same browsers, and at the same
 * time.
 *
 * Allowlist can contain:
 *   - IPv4 addresses or subnets in CIDR notation
 *   - IPv6 addresses or subnets in CIDR notation
 *
 * Default value: empty array.
 *
 * Sample configuration to allow all the traffic on the local network.
 *
 * @code
 * $settings['crawler_rate_limit.settings']['ip_address_allowlist'] = [
 *   '127.0.0.1',
 *   '10.0.0.0/8',
 *   '192.168.1.0/24',
 * ];
 * @endcode
 */
$settings['crawler_rate_limit.settings']['ip_address_allowlist'] = [];

/**
 * List of ASNs that should be blocked. Optional.
 *
 * All requests coming from IP addresses belonging to the ASNs on this list
 * will be blocked. Server will respond with HTTP code 403. Useful as a
 * drastic, and ideally temporary measure if ASN can be identified as origin
 * of exclusively unwanted traffic.
 *
 * Requires geoip2/geoip2 package and associated ASN Database. Make sure
 * that ASN database path is configured correctly if you want to use ASN
 * blocking.
 *
 * Note that blocking takes precedence over rate limiting and allowlist. If
 * request comes from the IP address belonging to the ASN found on the
 * blocklist, server will immediately return 403 response. Rate limiting
 * settings and allowlist will not be considered.
 *
 * Caution: Autonomous Systems are large networks. Carefully analyze and
 * understand your website traffic in order to make sure that blocking an
 * ASN won't block genuine visitor traffic.
 *
 * Sample list blocking 3 ASNs taken from the Spamhaus DROP list.
 * @see: https://www.spamhaus.org/blocklists/do-not-route-or-peer/
 *
 * @code
 * $settings['crawler_rate_limit.settings']['asn_blocklist'] = [
 *   24567,
 *   202469,
 *   401616,
 * ];
 * @endcode
 */
$settings['crawler_rate_limit.settings']['asn_blocklist'] = [
  '216071',
  '24940',
  '46918',
  '14061',
  '52449',
  '24560',
];
