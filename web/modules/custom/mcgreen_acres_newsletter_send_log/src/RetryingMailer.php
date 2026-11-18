<?php

namespace Drupal\mcgreen_acres_newsletter_send_log;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\simplenews\AbortSendingException;
use Drupal\simplenews\Mail\Mailer;
use Drupal\simplenews\Mail\MailInterface;
use Drupal\simplenews\Spool\SpoolStorageInterface;

/**
 * Simplenews mailer that retries dropped connections and logs each failure.
 *
 * Simplenews treats any failed send as unclassified and leaves the spool row
 * pending; at the end of the batch, if any other mail in it went out, every
 * pending failure is converted to permanently failed. A single dropped SMTP
 * connection therefore costs the recipient their copy for good.
 *
 * For newsletter issue mail this class:
 * - retries transient transport errors immediately, a couple of times, with a
 *   short backoff (each send opens a fresh connection, so a retry usually
 *   works);
 * - if the retries are exhausted and nothing has been sent yet in this batch,
 *   assumes the transport is down and aborts the batch, which releases the
 *   rows to be retried on the next cron run;
 * - if the retries are exhausted but earlier mails in the batch did go out,
 *   returns the Simplenews default (pending, then failed at the end of the
 *   batch), because the problem looks specific to this recipient;
 * - gives up with a permanent failure once the recipient has failed
 *   max_total_attempts times across cron runs, so a stuck row cannot loop
 *   forever;
 * - leaves errors it does not recognize as transport problems to the
 *   Simplenews default.
 *
 * Every failed attempt, and the success of any recipient that needed a retry,
 * is written to the send log.
 */
class RetryingMailer extends Mailer {

  /**
   * The send attempt log.
   */
  protected SendAttemptStorage $attemptStorage;

  /**
   * Captures the mailer's error message for the send that just failed.
   */
  protected SendErrorCollector $errorCollector;

  /**
   * Decides whether an error is a transient transport problem.
   */
  protected TransportErrorClassifier $classifier;

  /**
   * The config factory, for the send log settings.
   */
  protected ConfigFactoryInterface $logConfigFactory;

  /**
   * Whether a mail has been sent successfully during the current batch.
   */
  protected bool $sentInBatch = FALSE;

  /**
   * Injects the send log dependencies (see the service provider).
   */
  public function setSendLog(SendAttemptStorage $attempt_storage, SendErrorCollector $error_collector, TransportErrorClassifier $classifier, ConfigFactoryInterface $config_factory): void {
    $this->attemptStorage = $attempt_storage;
    $this->errorCollector = $error_collector;
    $this->classifier = $classifier;
    $this->logConfigFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function sendSpool($limit = SpoolStorageInterface::UNLIMITED, array $conditions = []) {
    $this->sentInBatch = FALSE;
    return parent::sendSpool($limit, $conditions);
  }

  /**
   * {@inheritdoc}
   */
  public function sendMail(MailInterface $mail) {
    // Only issue mail is retried and logged; confirmation and validation
    // mails are sent one at a time on request, not from the spool.
    if ($mail->getKey() !== 'node') {
      return parent::sendMail($mail);
    }

    $settings = $this->logConfigFactory->get('mcgreen_acres_newsletter_send_log.settings');
    $max_retries = (int) $settings->get('in_place_retries');
    $max_total = (int) $settings->get('max_total_attempts');
    $delay = (int) $settings->get('retry_delay');

    $issue = $mail->getIssue();
    $entity_type = $issue->getEntityTypeId();
    $entity_id = (int) $issue->id();
    $address = (string) $mail->getRecipient();
    $snid = $mail->getSubscriber()->id();
    $snid = $snid ? (int) $snid : NULL;

    $previous_errors = $this->attemptStorage->countErrors($entity_type, $entity_id, $address);
    $retries = 0;

    while (TRUE) {
      $attempt = $previous_errors + $retries + 1;
      $this->errorCollector->reset();
      $result = parent::sendMail($mail);

      if ($result !== SpoolStorageInterface::STATUS_PENDING) {
        if ($result === SpoolStorageInterface::STATUS_DONE) {
          $this->sentInBatch = TRUE;
          if ($attempt > 1) {
            $this->attemptStorage->record($entity_type, $entity_id, $snid, $address, $attempt, 'sent');
          }
        }
        return $result;
      }

      // Pending means the send failed.
      $error = $this->errorCollector->getLastError() ?? 'Unknown error (no message was logged by the mailer).';
      $classification = $this->classifier->classify($error);
      $this->attemptStorage->record($entity_type, $entity_id, $snid, $address, $attempt, 'error', $classification, $error);

      if ($classification !== TransportErrorClassifier::TRANSIENT) {
        // Not a transport problem we recognize: Simplenews default.
        return $result;
      }

      if ($attempt >= $max_total) {
        return SpoolStorageInterface::STATUS_FAILED;
      }

      if ($retries < $max_retries) {
        $retries++;
        $this->pause($delay * $retries);
        continue;
      }

      if ($this->sentInBatch) {
        // The transport works for others, so this looks recipient-specific.
        return $result;
      }

      throw new AbortSendingException(sprintf('Sending paused after repeated transport errors with nothing sent in this batch (%s). The remaining mails will be retried on the next cron run.', $error));
    }
  }

  /**
   * Waits between immediate retries.
   *
   * @param int $seconds
   *   Seconds to wait. Overridden in tests.
   */
  protected function pause(int $seconds): void {
    if ($seconds > 0) {
      sleep($seconds);
    }
  }

}
