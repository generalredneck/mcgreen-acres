<?php

namespace Drupal\Tests\mcgreen_acres_newsletter_send_log\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simplenews\Spool\SpoolStorageInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests requeueing failed recipients.
 *
 * @group mcgreen_acres_newsletter_send_log
 * @covers \Drupal\mcgreen_acres_newsletter_send_log\SendRequeuer
 * @covers \Drupal\mcgreen_acres_newsletter_send_log\SendAttemptStorage
 */
#[RunTestsInSeparateProcesses]
class SendRequeuerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'simplenews',
    'mcgreen_acres_newsletter_send_log',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'mcgreen_acres_newsletter_send_log']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('simplenews_subscriber');
    $this->installEntitySchema('simplenews_subscriber_history');
    $this->installSchema('simplenews', ['simplenews_mail_spool']);
    $this->installSchema('mcgreen_acres_newsletter_send_log', ['mcgreen_newsletter_send_attempt']);
  }

  /**
   * Adds a spool row for a new subscriber and returns [msid, snid].
   */
  protected function spoolRow(string $mail, int $issue, int $status): array {
    $subscriber = Subscriber::create(['mail' => $mail]);
    $subscriber->save();
    $msid = $this->container->get('database')->insert('simplenews_mail_spool')
      ->fields([
        'entity_type' => 'node',
        'entity_id' => $issue,
        'newsletter_id' => 'default',
        'status' => $status,
        'timestamp' => 1000,
        'data' => '',
        'snid' => $subscriber->id(),
      ])
      ->execute();
    return [(int) $msid, (int) $subscriber->id()];
  }

  /**
   * Returns the status of a spool row.
   */
  protected function spoolStatus(int $msid): int {
    return (int) $this->container->get('database')->select('simplenews_mail_spool', 's')
      ->fields('s', ['status'])
      ->condition('msid', $msid)
      ->execute()
      ->fetchField();
  }

  /**
   * Only failed rows of the given issue are requeued.
   */
  public function testOnlyFailedRowsOfTheIssueAreRequeued(): void {
    [$failed_a] = $this->spoolRow('a@example.com', 5, SpoolStorageInterface::STATUS_FAILED);
    [$failed_other_issue] = $this->spoolRow('b@example.com', 6, SpoolStorageInterface::STATUS_FAILED);
    [$pending] = $this->spoolRow('c@example.com', 5, SpoolStorageInterface::STATUS_PENDING);
    [$failed_d] = $this->spoolRow('d@example.com', 5, SpoolStorageInterface::STATUS_FAILED);

    $count = $this->container->get('mcgreen_acres_newsletter_send_log.requeuer')
      ->requeueFailed('node', 5, [$failed_a, $failed_other_issue, $pending, $failed_d, 9999]);

    $this->assertSame(2, $count);
    $this->assertSame(SpoolStorageInterface::STATUS_PENDING, $this->spoolStatus($failed_a));
    $this->assertSame(SpoolStorageInterface::STATUS_PENDING, $this->spoolStatus($failed_d));
    $this->assertSame(SpoolStorageInterface::STATUS_FAILED, $this->spoolStatus($failed_other_issue));
    $this->assertSame(SpoolStorageInterface::STATUS_PENDING, $this->spoolStatus($pending));

    $logged = $this->container->get('mcgreen_acres_newsletter_send_log.storage')->loadForIssue('node', 5);
    $this->assertSame(['requeued', 'requeued'], array_column($logged, 'outcome'));
    $this->assertEqualsCanonicalizing(['a@example.com', 'd@example.com'], array_column($logged, 'mail'));
  }

  /**
   * Requeueing nothing is a no-op.
   */
  public function testEmptySelection(): void {
    $this->assertSame(0, $this->container->get('mcgreen_acres_newsletter_send_log.requeuer')->requeueFailed('node', 5, []));
  }

  /**
   * A requeue gives the recipient a fresh set of attempts.
   */
  public function testRequeueResetsTheFailureCount(): void {
    $storage = $this->container->get('mcgreen_acres_newsletter_send_log.storage');
    for ($i = 1; $i <= 4; $i++) {
      $storage->record('node', 5, 7, 'a@example.com', $i, 'error', 'transient', 'x');
    }
    $this->assertSame(4, $storage->countErrors('node', 5, 'a@example.com'));

    $storage->recordRequeue('node', 5, 7, 'a@example.com');
    $this->assertSame(0, $storage->countErrors('node', 5, 'a@example.com'));

    $storage->record('node', 5, 7, 'a@example.com', 1, 'error', 'transient', 'x');
    $this->assertSame(1, $storage->countErrors('node', 5, 'a@example.com'));
    // Another recipient is unaffected.
    $this->assertSame(0, $storage->countErrors('node', 5, 'b@example.com'));
  }

  /**
   * Issue counters follow the requeue.
   */
  public function testAdjustIssueCounters(): void {
    $requeuer = $this->container->get('mcgreen_acres_newsletter_send_log.requeuer');

    $item = (object) ['status' => SIMPLENEWS_STATUS_SEND_READY, 'error_count' => 4];
    $requeuer->adjustIssueCounters($item, 3);
    $this->assertSame(1, $item->error_count);
    $this->assertSame(SIMPLENEWS_STATUS_SEND_PENDING, $item->status);

    // The error count never goes negative, and a pending issue stays pending.
    $item = (object) ['status' => SIMPLENEWS_STATUS_SEND_PENDING, 'error_count' => 1];
    $requeuer->adjustIssueCounters($item, 5);
    $this->assertSame(0, $item->error_count);
    $this->assertSame(SIMPLENEWS_STATUS_SEND_PENDING, $item->status);
  }

}
