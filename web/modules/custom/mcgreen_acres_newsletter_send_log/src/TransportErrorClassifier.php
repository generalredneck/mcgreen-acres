<?php

namespace Drupal\mcgreen_acres_newsletter_send_log;

/**
 * Decides whether a mail error looks like a transient transport problem.
 *
 * Only errors that say something about the connection itself (dropped,
 * refused, timed out, SMTP 4xx "try again later") count as transient. Anything
 * else — including SMTP 5xx replies such as a provider's daily sending limit —
 * is left to Simplenews' default handling, so we never mass-fail or
 * mass-retry on a message we don't understand.
 */
class TransportErrorClassifier {

  const TRANSIENT = 'transient';

  const OTHER = 'other';

  /**
   * Patterns (case-insensitive) that indicate a transient transport error.
   */
  const TRANSIENT_PATTERNS = [
    '/closed unexpectedly/i',
    '/connection (?:refused|reset|timed out|timeout|lost|aborted)/i',
    '/could not be established/i',
    '/unable to connect/i',
    '/timed out/i',
    '/stream_socket_client|failed to connect/i',
    '/broken pipe/i',
    '/too many (?:connections|sessions|concurrent)/i',
    '/service (?:is )?(?:not available|unavailable)/i',
    '/temporar(?:y|ily)/i',
    // Symfony reports unexpected SMTP replies as: Expected response code
    // "250" but got code "421", with message "...".
    '/but got code "4\d\d"/i',
  ];

  /**
   * Classifies an error message.
   *
   * @param string $message
   *   The transport error message.
   *
   * @return string
   *   One of the class constants.
   */
  public function classify(string $message): string {
    foreach (self::TRANSIENT_PATTERNS as $pattern) {
      if (preg_match($pattern, $message)) {
        return self::TRANSIENT;
      }
    }
    return self::OTHER;
  }

}
