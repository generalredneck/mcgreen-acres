<?php

namespace Drupal\mcgreen_acres_newsletter_send_log;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Stores and reads the per-recipient newsletter send attempt log.
 */
class SendAttemptStorage {

  const TABLE = 'mcgreen_newsletter_send_attempt';

  public function __construct(
    protected Connection $database,
    protected TimeInterface $time,
  ) {}

  /**
   * Records one attempt.
   *
   * @param string $entity_type
   *   The newsletter issue's entity type.
   * @param int $entity_id
   *   The newsletter issue's ID.
   * @param int|null $snid
   *   The subscriber ID, if saved.
   * @param string $mail
   *   The recipient address.
   * @param int $attempt
   *   The attempt number for this recipient on this issue, from 1.
   * @param string $outcome
   *   Either 'error' or 'sent'.
   * @param string|null $classification
   *   The error classification, for errors.
   * @param string|null $error
   *   The error message, for errors.
   */
  public function record(string $entity_type, int $entity_id, ?int $snid, string $mail, int $attempt, string $outcome, ?string $classification = NULL, ?string $error = NULL): void {
    $this->database->insert(self::TABLE)
      ->fields([
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'snid' => $snid ?: NULL,
        'mail' => $mail,
        'attempt' => $attempt,
        'timestamp' => $this->time->getRequestTime(),
        'outcome' => $outcome,
        'classification' => $classification,
        'error' => $error,
      ])
      ->execute();
  }

  /**
   * Records that staff put a failed recipient back in the send queue.
   *
   * This also resets the recipient's failure count, so the give-up limit
   * starts over.
   */
  public function recordRequeue(string $entity_type, int $entity_id, ?int $snid, string $mail): void {
    $this->record($entity_type, $entity_id, $snid, $mail, 0, 'requeued');
  }

  /**
   * Counts the failed attempts for a recipient since the last requeue.
   */
  public function countErrors(string $entity_type, int $entity_id, string $mail): int {
    $requeue = $this->database->select(self::TABLE, 'a')
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->condition('mail', $mail)
      ->condition('outcome', 'requeued');
    $requeue->addExpression('MAX(id)', 'last_id');
    $last_requeue = (int) $requeue->execute()->fetchField();

    return (int) $this->database->select(self::TABLE, 'a')
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->condition('mail', $mail)
      ->condition('outcome', 'error')
      ->condition('id', $last_requeue, '>')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Loads every logged attempt for an issue, oldest first.
   *
   * @return object[]
   *   Attempt rows.
   */
  public function loadForIssue(string $entity_type, int $entity_id): array {
    return $this->database->select(self::TABLE, 'a')
      ->fields('a')
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->orderBy('timestamp')
      ->orderBy('id')
      ->execute()
      ->fetchAll();
  }

  /**
   * Deletes all attempts for an issue.
   */
  public function deleteForIssue(string $entity_type, int $entity_id): void {
    $this->database->delete(self::TABLE)
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->execute();
  }

  /**
   * Deletes attempts older than the given number of days.
   *
   * @return int
   *   The number of rows deleted.
   */
  public function purge(int $days): int {
    return (int) $this->database->delete(self::TABLE)
      ->condition('timestamp', $this->time->getRequestTime() - $days * 86400, '<')
      ->execute();
  }

}
