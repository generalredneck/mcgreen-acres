<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats_unsubscribe;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;

/**
 * Generates and validates signed campaign-attribution tokens.
 *
 * These bind a subscriber's email to a specific campaign entity so that an
 * unsubscribe click can be traced back to the newsletter issue that
 * prompted it, without exposing a guessable/enumerable identifier.
 */
class CampaignHash {

  public function __construct(
    protected PrivateKey $privateKey,
  ) {}

  /**
   * Generates a signed hash binding a subscriber to a campaign entity.
   *
   * @param string $mail
   *   The subscriber's email address.
   * @param string $entityType
   *   The campaign entity type id (e.g. 'node').
   * @param string $entityId
   *   The campaign entity id.
   *
   * @return string
   *   The signed hash.
   */
  public function generate(string $mail, string $entityType, string $entityId): string {
    $data = implode(':', [$mail, $entityType, $entityId]);
    return Crypt::hmacBase64($data, $this->privateKey->get() . Settings::getHashSalt());
  }

  /**
   * Validates a previously generated campaign hash.
   *
   * @param string $mail
   *   The subscriber's email address.
   * @param string $entityType
   *   The campaign entity type id (e.g. 'node').
   * @param string $entityId
   *   The campaign entity id.
   * @param string $hash
   *   The hash to validate.
   *
   * @return bool
   *   TRUE if the hash matches, FALSE otherwise.
   */
  public function isValid(string $mail, string $entityType, string $entityId, string $hash): bool {
    return hash_equals($this->generate($mail, $entityType, $entityId), $hash);
  }

}
