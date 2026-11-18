<?php

namespace Drupal\mcgreen_acres_newsletter_send_log\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mcgreen_acres_newsletter_send_log\SendAttemptStorage;
use Drupal\mcgreen_acres_newsletter_send_log\SendRequeuer;
use Drupal\node\NodeInterface;
use Drupal\simplenews\Spool\SpoolStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the delivery problems of a newsletter issue, per recipient.
 *
 * Recipients that Simplenews marked permanently failed get a checkbox, and
 * the selected ones can be put back in the send queue.
 */
class SendDetailsForm extends FormBase {

  public function __construct(
    protected SendAttemptStorage $attempts,
    protected SendRequeuer $requeuer,
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
      $container->get('mcgreen_acres_newsletter_send_log.requeuer'),
      $container->get('simplenews.spool_storage'),
      $container->get('database'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcgreen_acres_newsletter_send_log_details';
  }

  /**
   * Title callback.
   */
  public function title(NodeInterface $node): string {
    return (string) $this->t('Send details: @title', ['@title' => $node->getTitle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $form_state->set('node', $node);
    $summary = $this->spoolStorage->issueSummary($node);
    $settings = $this->config('mcgreen_acres_newsletter_send_log.settings');

    $form['summary'] = [
      '#markup' => '<p>' . $summary['description'] . ' '
      . $this->t('<a href=":url">Back to the Newsletter tab</a>.', [
        ':url' => Url::fromRoute('simplenews.node_tab', ['node' => $node->id()])->toString(),
      ]) . '</p>',
    ];
    $form['policy'] = [
      '#markup' => '<p>' . $this->t('Dropped connections and other transport errors are retried immediately up to @retries more times; if the mail server still will not accept the message, the recipient stays queued and is tried again on later cron runs, up to @total attempts in all. Other errors are handled by Simplenews: the recipient is marked failed and is not retried automatically, but can be requeued below.', [
        '@retries' => $settings->get('in_place_retries'),
        '@total' => $settings->get('max_total_attempts'),
      ]) . '</p>',
    ];

    $options = [];
    $has_failed = FALSE;
    foreach ($this->collectRecipients($node) as $index => $recipient) {
      $failed = $recipient['spool_status'] === SpoolStorageInterface::STATUS_FAILED && !empty($recipient['msid']);
      $has_failed = $has_failed || $failed;
      $key = $failed ? (string) $recipient['msid'] : 'r' . $index;
      $options[$key] = $this->buildRow($recipient);
      // Only failed recipients can be requeued.
      $options[$key]['#disabled'] = !$failed;
    }

    $form['recipients'] = [
      '#type' => 'tableselect',
      '#header' => [
        'mail' => $this->t('Recipient'),
        'status' => $this->t('Status'),
        'retries' => $this->t('Retries'),
        'last' => $this->t('Last attempt'),
        'attempts' => $this->t('Attempts'),
      ],
      '#options' => $options,
      '#empty' => $this->t('No failed deliveries recorded for this issue.'),
    ];

    if ($has_failed) {
      $form['actions']['#type'] = 'actions';
      $form['actions']['requeue'] = [
        '#type' => 'submit',
        '#value' => $this->t('Requeue selected'),
        '#button_type' => 'primary',
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!array_filter($form_state->getValue('recipients') ?? [])) {
      $form_state->setErrorByName('recipients', $this->t('Select at least one failed recipient to requeue.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node = $form_state->get('node');
    $msids = array_keys(array_filter($form_state->getValue('recipients')));
    $count = $this->requeuer->requeueFailed('node', (int) $node->id(), $msids);

    if (!$count) {
      $this->messenger()->addWarning($this->t('Nothing was requeued: the selected recipients are no longer marked failed.'));
      return;
    }

    $this->requeuer->adjustIssueCounters($node->simplenews_issue, $count);
    $node->save();
    $this->logger('simplenews')->notice('Requeued @count failed recipient(s) of newsletter issue @nid.', [
      '@count' => $count,
      '@nid' => $node->id(),
    ]);
    $this->messenger()->addStatus($this->formatPlural($count,
      '1 recipient was put back in the send queue. It will be sent on the next cron run.',
      '@count recipients were put back in the send queue. They will be sent on the next cron run.'));
  }

  /**
   * Groups logged attempts and spool state by recipient.
   *
   * @return array[]
   *   Recipients sorted with permanent failures first, then by most recent
   *   attempt. Each has keys: mail, msid, spool_status, attempts, last.
   */
  protected function collectRecipients(NodeInterface $node): array {
    $recipients = [];
    foreach ($this->attempts->loadForIssue('node', (int) $node->id()) as $row) {
      $key = mb_strtolower($row->mail);
      $recipients[$key]['mail'] = $row->mail;
      $recipients[$key]['msid'] = NULL;
      $recipients[$key]['spool_status'] = NULL;
      $recipients[$key]['attempts'][] = $row;
      $recipients[$key]['last'] = (int) $row->timestamp;
    }

    // Current queue state, plus failures that predate the log.
    $query = $this->database->select('simplenews_mail_spool', 's');
    $query->join('simplenews_subscriber', 'sub', 'sub.id = s.snid');
    $query->fields('s', ['msid', 'status', 'timestamp'])
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
      $recipients[$key]['msid'] = (int) $row->msid;
      $recipients[$key]['spool_status'] = (int) $row->status;
      $recipients[$key]['last'] ??= (int) $row->timestamp;
    }

    uasort($recipients, function (array $a, array $b): int {
      $a_failed = $a['spool_status'] === SpoolStorageInterface::STATUS_FAILED ? 0 : 1;
      $b_failed = $b['spool_status'] === SpoolStorageInterface::STATUS_FAILED ? 0 : 1;
      return [$a_failed, -$a['last']] <=> [$b_failed, -$b['last']];
    });
    return array_values($recipients);
  }

  /**
   * Builds the cells of one recipient row.
   */
  protected function buildRow(array $recipient): array {
    $attempts = $recipient['attempts'];
    $errors = array_filter($attempts, fn($a) => $a->outcome === 'error');

    $items = [];
    foreach ($attempts as $attempt) {
      $when = $this->formatTime((int) $attempt->timestamp);
      $items[] = match ($attempt->outcome) {
        'sent' => $this->t('#@n @when: sent', ['@n' => $attempt->attempt, '@when' => $when]),
        'requeued' => $this->t('@when: requeued manually', ['@when' => $when]),
        default => $this->t('#@n @when: @error', [
          '@n' => $attempt->attempt,
          '@when' => $when,
          '@error' => $attempt->error ?: $this->t('(no message)'),
        ]),
      };
    }
    if (!$attempts) {
      $items[] = $this->t('No details were recorded for this failure.');
    }

    return [
      'mail' => $recipient['mail'],
      'status' => $this->statusLabel($recipient),
      'retries' => count($errors),
      'last' => $this->formatTime((int) $recipient['last']),
      'attempts' => ['data' => ['#theme' => 'item_list', '#items' => $items]],
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
