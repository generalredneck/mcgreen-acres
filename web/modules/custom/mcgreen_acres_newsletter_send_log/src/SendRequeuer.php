<?php

namespace Drupal\mcgreen_acres_newsletter_send_log;

use Drupal\Core\Database\Connection;
use Drupal\simplenews\Spool\SpoolStorageInterface;

/**
 * Puts permanently failed recipients back in the Simplenews send queue.
 */
class SendRequeuer {

  public function __construct(
    protected Connection $database,
    protected SpoolStorageInterface $spoolStorage,
    protected SendAttemptStorage $attempts,
  ) {}

  /**
   * Requeues failed spool rows of an issue.
   *
   * The given IDs are re-checked against the database: only rows that belong
   * to the issue and are currently failed are touched, so a stale or forged
   * selection cannot requeue anything else.
   *
   * @param string $entity_type
   *   The issue's entity type.
   * @param int $entity_id
   *   The issue's ID.
   * @param int[] $msids
   *   Spool row IDs to requeue.
   *
   * @return int
   *   The number of recipients requeued.
   */
  public function requeueFailed(string $entity_type, int $entity_id, array $msids): int {
    $msids = array_values(array_filter(array_map('intval', $msids)));
    if (!$msids) {
      return 0;
    }

    $query = $this->database->select('simplenews_mail_spool', 's');
    $query->leftJoin('simplenews_subscriber', 'sub', 'sub.id = s.snid');
    $rows = $query->fields('s', ['msid', 'snid'])
      ->fields('sub', ['mail'])
      ->condition('s.msid', $msids, 'IN')
      ->condition('s.entity_type', $entity_type)
      ->condition('s.entity_id', $entity_id)
      ->condition('s.status', SpoolStorageInterface::STATUS_FAILED)
      ->execute()
      ->fetchAll();
    if (!$rows) {
      return 0;
    }

    $this->spoolStorage->updateMails(array_column($rows, 'msid'), SpoolStorageInterface::STATUS_PENDING);
    foreach ($rows as $row) {
      if ($row->mail !== NULL) {
        $this->attempts->recordRequeue($entity_type, $entity_id, $row->snid ? (int) $row->snid : NULL, $row->mail);
      }
    }
    return count($rows);
  }

  /**
   * Keeps an issue's counters consistent after a requeue.
   *
   * Simplenews adds failures to the issue's error count as they happen and
   * never removes them, so requeued recipients are taken back off it. An issue
   * already marked fully sent goes back to pending so Simplenews marks it sent
   * again once the requeued mail is delivered.
   *
   * @param object $issue_item
   *   The issue's simplenews_issue field item.
   * @param int $requeued
   *   How many recipients were requeued.
   */
  public function adjustIssueCounters(object $issue_item, int $requeued): void {
    $issue_item->error_count = max(0, (int) $issue_item->error_count - $requeued);
    if ((int) $issue_item->status === SIMPLENEWS_STATUS_SEND_READY) {
      $issue_item->status = SIMPLENEWS_STATUS_SEND_PENDING;
    }
  }

}
