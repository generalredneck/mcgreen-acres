<?php

namespace Drupal\mcgreen_webform_network_check;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mcgreen_webform_network_check\Plugin\WebformHandler\NetworkCheckWebformHandler;
use Drupal\webform\WebformSubmissionInterface;
use Psr\Log\LoggerInterface;

/**
 * Emails a digest of newly quarantined submissions and purges old ones.
 */
class QuarantineReporter {

  /**
   * State key: created time of the newest submission already reported.
   */
  const STATE_REPORTED_THROUGH = 'mcgreen_webform_network_check.reported_through';

  /**
   * State key: when the last digest was sent.
   */
  const STATE_LAST_SENT = 'mcgreen_webform_network_check.last_sent';

  /**
   * Maximum submissions listed individually in one digest.
   */
  const MAX_LISTED = 50;

  /**
   * Maximum submissions deleted per cron run.
   */
  const PURGE_BATCH = 100;

  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected StateInterface $state,
    protected MailManagerInterface $mailManager,
    protected NetworkClassifier $classifier,
    protected DateFormatterInterface $dateFormatter,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Runs the digest and purge. Called from hook_cron().
   *
   * @param int $now
   *   The current timestamp.
   */
  public function run(int $now): void {
    $this->sendDigest($now);
    $this->purge($now);
  }

  /**
   * Emails a summary of submissions quarantined since the last digest.
   *
   * Sends nothing when there is nothing new, and at most once per
   * notify_interval so an attack produces one email, not hundreds.
   *
   * @param int $now
   *   The current timestamp.
   *
   * @return int
   *   The number of submissions reported (0 if no email was sent).
   */
  public function sendDigest(int $now): int {
    $config = $this->configFactory->get('mcgreen_webform_network_check.settings');
    $last_sent = (int) $this->state->get(static::STATE_LAST_SENT, 0);
    if ($now - $last_sent < (int) $config->get('notify_interval')) {
      return 0;
    }

    $since = (int) $this->state->get(static::STATE_REPORTED_THROUGH, 0);
    $sids = $this->quarantinedQuery()
      ->condition('s.created', $since, '>')
      ->condition('s.created', $now, '<=')
      ->orderBy('s.created')
      ->execute()
      ->fetchCol();
    if (!$sids) {
      return 0;
    }

    /** @var \Drupal\webform\WebformSubmissionInterface[] $submissions */
    $submissions = $this->entityTypeManager->getStorage('webform_submission')->loadMultiple($sids);
    $to = $config->get('notify_email') ?: $this->configFactory->get('system.site')->get('mail');
    $params = [
      'subject' => $this->formatPlural(count($submissions), '1 form submission quarantined from a hosting network', '@count form submissions quarantined from hosting networks'),
      'body' => $this->buildDigest($submissions, $since, (int) $config->get('retention_days')),
    ];
    $result = $this->mailManager->mail('mcgreen_webform_network_check', 'digest', $to, 'en', $params);
    if (empty($result['result'])) {
      // Leave state untouched so the next cron run retries.
      $this->logger->error('Could not send the quarantine digest to @to.', ['@to' => $to]);
      return 0;
    }

    $newest = max(array_map(fn(WebformSubmissionInterface $s) => (int) $s->getCreatedTime(), $submissions));
    $this->state->setMultiple([
      static::STATE_REPORTED_THROUGH => $newest,
      static::STATE_LAST_SENT => $now,
    ]);
    return count($submissions);
  }

  /**
   * Deletes quarantined submissions older than the retention period.
   *
   * Only submissions already included in a digest are deleted, so nothing
   * disappears without having been reported.
   *
   * @param int $now
   *   The current timestamp.
   *
   * @return int
   *   The number of submissions deleted.
   */
  public function purge(int $now): int {
    $days = (int) $this->configFactory->get('mcgreen_webform_network_check.settings')->get('retention_days');
    if ($days <= 0) {
      return 0;
    }
    $cutoff = min($now - $days * 86400, (int) $this->state->get(static::STATE_REPORTED_THROUGH, 0));
    $sids = $this->quarantinedQuery()
      ->condition('s.created', $cutoff, '<=')
      ->range(0, static::PURGE_BATCH)
      ->execute()
      ->fetchCol();
    if (!$sids) {
      return 0;
    }
    $storage = $this->entityTypeManager->getStorage('webform_submission');
    $storage->delete($storage->loadMultiple($sids));
    $this->logger->info('Purged @count quarantined webform submissions.', ['@count' => count($sids)]);
    return count($sids);
  }

  /**
   * Builds a query for the IDs of quarantined submissions.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   A select on webform_submission (alias "s") returning sids.
   */
  protected function quarantinedQuery() {
    $query = $this->database->select('webform_submission', 's');
    $query->innerJoin('webform_submission_data', 'd', 'd.sid = s.sid');
    $query->fields('s', ['sid'])
      ->condition('d.name', NetworkCheckWebformHandler::ELEMENT)
      ->condition('d.value', NetworkClassifier::HOSTING);
    return $query;
  }

  /**
   * Builds the digest's HTML body.
   *
   * @param \Drupal\webform\WebformSubmissionInterface[] $submissions
   *   The newly quarantined submissions, oldest first.
   * @param int $since
   *   Created time of the newest submission in the previous digest.
   * @param int $retention_days
   *   Days until quarantined submissions are purged.
   *
   * @return string
   *   HTML.
   */
  protected function buildDigest(array $submissions, int $since, int $retention_days): string {
    $by_form = [];
    $by_network = [];
    $rows = [];
    foreach ($submissions as $submission) {
      $webform = $submission->getWebform();
      $form_id = $webform->id();
      $by_form[$form_id] ??= [
        'label' => $webform->label(),
        'url' => $webform->toUrl('results-submissions', ['absolute' => TRUE])->toString(),
        'count' => 0,
      ];
      $by_form[$form_id]['count']++;

      $network = NetworkClassifier::networkLabel($this->classifier->classify((string) $submission->getRemoteAddr()));
      $by_network[$network] = ($by_network[$network] ?? 0) + 1;

      if (count($rows) < static::MAX_LISTED) {
        $data = $submission->getData();
        $name = trim(($data['first_name'] ?? $data['fname'] ?? '') . ' ' . ($data['last_name'] ?? $data['lname'] ?? ''));
        $rows[] = [
          $this->dateFormatter->format((int) $submission->getCreatedTime(), 'short'),
          $webform->label(),
          Unicode::truncate($name, 40, FALSE, TRUE),
          (string) ($data['email'] ?? ''),
          $network,
          $submission->toUrl('canonical', ['absolute' => TRUE])->toString(),
        ];
      }
    }
    arsort($by_network);

    $e = [Html::class, 'escape'];
    $first = reset($submissions)->getCreatedTime();
    $html = '<p>' . $e(sprintf(
      '%d submission(s) since %s came from hosting / data-center networks and were quarantined: the visitor saw the normal thank-you message, but no email was sent and no one was subscribed (Mailchimp or newsletter). They will be deleted automatically %d days after they were submitted.',
      count($submissions),
      $this->dateFormatter->format($since ?: (int) $first, 'short'),
      $retention_days,
    )) . '</p>';
    $html .= '<p>Most of these are bots. A real person on a VPN can land here too: if one looks genuine, open it and add them to the newsletter by hand.</p>';

    $html .= '<h3>By form</h3><ul>';
    foreach ($by_form as $form) {
      $html .= '<li><a href="' . $e($form['url']) . '">' . $e($form['label']) . '</a>: ' . $form['count'] . '</li>';
    }
    $html .= '</ul><h3>By network</h3><ul>';
    foreach ($by_network as $network => $count) {
      $html .= '<li>' . $e($network) . ': ' . $count . '</li>';
    }
    $html .= '</ul><h3>Submissions</h3>';
    $html .= '<table cellpadding="4" border="1" style="border-collapse:collapse"><tr><th>Submitted</th><th>Form</th><th>Name</th><th>Email</th><th>Network</th><th></th></tr>';
    foreach ($rows as $row) {
      [$when, $form, $name, $email, $network, $url] = $row;
      $html .= '<tr><td>' . $e($when) . '</td><td>' . $e($form) . '</td><td>' . $e($name) . '</td><td>' . $e($email) . '</td><td>' . $e($network) . '</td><td><a href="' . $e($url) . '">view</a></td></tr>';
    }
    $html .= '</table>';
    if (count($submissions) > count($rows)) {
      $html .= '<p>' . $e(sprintf('…and %d more; see the form results pages above.', count($submissions) - count($rows))) . '</p>';
    }
    return $html;
  }

  /**
   * Plural helper without a translation dependency.
   */
  protected function formatPlural(int $count, string $singular, string $plural): string {
    return $count === 1 ? $singular : str_replace('@count', (string) $count, $plural);
  }

}
