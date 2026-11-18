<?php

namespace Drupal\mcgreen_acres_newsletter_send_log\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\mcgreen_acres_newsletter_send_log\SendAttemptStorage;
use Drupal\node\NodeInterface;
use Drupal\simplenews\Spool\SpoolStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the delivery problems of a newsletter issue, per recipient.
 */
class SendDetailsController extends ControllerBase {

  public function __construct(
    protected SendAttemptStorage $attempts,
    protected SpoolStorageInterface $spoolStorage,
    protected Connection $database,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mcgreen_acres_newsletter_send_log.storage'),
      $container->get('simplenews.spool_storage'),
      $container->get('database'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Title callback.
   */
  public function title(NodeInterface $node): string {
    return (string) $this->t('Send details: @title', ['@title' => $node->getTitle()]);
  }

  /**
   * Page callback.
   */
  public function view(NodeInterface $node): array {
    $summary = $this->spoolStorage->issueSummary($node);
    $settings = $this->config('mcgreen_acres_newsletter_send_log.settings');

    $build['summary'] = [
      '#markup' => '<p>' . $summary['description'] . ' '
      . $this->t('<a href=":url">Back to the Newsletter tab</a>.', [
        ':url' => Url::fromRoute('simplenews.node_tab', ['node' => $node->id()])->toString(),
      ]) . '</p>',
    ];
    $build['policy'] = [
      '#markup' => '<p>' . $this->t('Dropped connections and other transport errors are retried immediately up to @retries more times; if the mail server still will not accept the message, the recipient stays queued and is tried again on later cron runs, up to @total attempts in all. Other errors are handled by Simplenews: the recipient is marked failed and is not retried.', [
        '@retries' => $settings->get('in_place_retries'),
        '@total' => $settings->get('max_total_attempts'),
      ]) . '</p>',
    ];

    $recipients = $this->collectRecipients($node);
    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Recipient'),
        $this->t('Status'),
        $this->t('Retries'),
        $this->t('Last attempt'),
        $this->t('Attempts'),
      ],
      '#rows' => [],
      '#empty' => $this->t('No failed deliveries recorded for this issue.'),
    ];
    foreach ($recipients as $recipient) {
      $build['table']['#rows'][] = $this->buildRow($recipient);
    }
    $build['#cache']['max-age'] = 0;
    return $build;
  }

  /**
   * Groups logged attempts and spool state by recipient.
   *
   * @return array[]
   *   Recipients sorted with permanent failures first, then by most recent
   *   attempt. Each has keys: mail, spool_status, attempts, last.
   */
  protected function collectRecipients(NodeInterface $node): array {
    $recipients = [];
    foreach ($this->attempts->loadForIssue('node', (int) $node->id()) as $row) {
      $key = mb_strtolower($row->mail);
      $recipients[$key]['mail'] = $row->mail;
      $recipients[$key]['spool_status'] = NULL;
      $recipients[$key]['attempts'][] = $row;
      $recipients[$key]['last'] = (int) $row->timestamp;
    }

    // Current queue state, plus failures that predate the log.
    $query = $this->database->select('simplenews_mail_spool', 's');
    $query->join('simplenews_subscriber', 'sub', 'sub.id = s.snid');
    $query->fields('s', ['status', 'timestamp'])
      ->fields('sub', ['mail'])
      ->condition('s.entity_type', 'node')
      ->condition('s.entity_id', $node->id());
    $or = $query->orConditionGroup()
      ->condition('s.status', SpoolStorageInterface::STATUS_FAILED);
    if ($recipients) {
      $or->condition('sub.mail', array_column($recipients, 'mail'), 'IN');
    }
    $query->condition($or);
    foreach ($query->execute() as $row) {
      $key = mb_strtolower($row->mail);
      $recipients[$key]['mail'] ??= $row->mail;
      $recipients[$key]['attempts'] ??= [];
      $recipients[$key]['spool_status'] = (int) $row->status;
      $recipients[$key]['last'] ??= (int) $row->timestamp;
    }

    uasort($recipients, function (array $a, array $b): int {
      $a_failed = $a['spool_status'] === SpoolStorageInterface::STATUS_FAILED ? 0 : 1;
      $b_failed = $b['spool_status'] === SpoolStorageInterface::STATUS_FAILED ? 0 : 1;
      return [$a_failed, -$a['last']] <=> [$b_failed, -$b['last']];
    });
    return $recipients;
  }

  /**
   * Builds one table row.
   */
  protected function buildRow(array $recipient): array {
    $attempts = $recipient['attempts'];
    $errors = array_filter($attempts, fn($a) => $a->outcome === 'error');

    $items = [];
    foreach ($attempts as $attempt) {
      $when = $this->formatTime((int) $attempt->timestamp);
      if ($attempt->outcome === 'sent') {
        $items[] = $this->t('#@n @when: sent', ['@n' => $attempt->attempt, '@when' => $when]);
      }
      else {
        $items[] = $this->t('#@n @when: @error', [
          '@n' => $attempt->attempt,
          '@when' => $when,
          '@error' => $attempt->error ?: $this->t('(no message)'),
        ]);
      }
    }
    if (!$attempts) {
      $items[] = $this->t('No details were recorded for this failure.');
    }

    return [
      $recipient['mail'],
      $this->statusLabel($recipient),
      $errors ? count($errors) : 0,
      $this->formatTime((int) $recipient['last']),
      ['data' => ['#theme' => 'item_list', '#items' => $items]],
    ];
  }

  /**
   * Describes where a recipient stands now.
   */
  protected function statusLabel(array $recipient) {
    $last = $recipient['attempts'] ? end($recipient['attempts']) : NULL;
    return match ($recipient['spool_status']) {
      SpoolStorageInterface::STATUS_FAILED => $this->t('Failed, will not be retried'),
      SpoolStorageInterface::STATUS_PENDING, SpoolStorageInterface::STATUS_HOLD => $this->t('Waiting to be retried'),
      SpoolStorageInterface::STATUS_IN_PROGRESS => $this->t('In progress'),
      SpoolStorageInterface::STATUS_DONE => $this->t('Sent'),
      SpoolStorageInterface::STATUS_SKIPPED => $this->t('Skipped'),
      default => ($last && $last->outcome === 'sent') ? $this->t('Sent after retrying') : $this->t('No longer queued'),
    };
  }

  /**
   * Formats a timestamp with seconds.
   */
  protected function formatTime(int $timestamp): string {
    return $this->dateFormatter->format($timestamp, 'custom', 'M j, Y g:i:s a');
  }

}
