<?php

namespace Drupal\mcgreen_acres_newsletter_send_log;

use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

/**
 * Captures the last Symfony Mailer send error as it is logged.
 *
 * Symfony Mailer swallows the transport exception and reports failure only as
 * a FALSE mail result plus a watchdog entry, so listening to that entry is the
 * way to learn why a send failed. The entry itself does not name the
 * recipient; the retrying mailer pairs the captured message with the
 * recipient it was sending to.
 */
class SendErrorCollector implements LoggerInterface {

  use RfcLoggerTrait;

  /**
   * The most recent error message, or NULL if none since the last reset.
   */
  protected ?string $lastError = NULL;

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    if (($context['channel'] ?? NULL) !== 'symfony_mailer') {
      return;
    }
    if (!str_starts_with((string) $message, 'Error sending email')) {
      return;
    }
    $this->lastError = (string) ($context['%message'] ?? $message);
  }

  /**
   * Forgets any captured error.
   */
  public function reset(): void {
    $this->lastError = NULL;
  }

  /**
   * Gets the last captured error.
   *
   * @return string|null
   *   The error message, or NULL if no error was logged since the last reset.
   */
  public function getLastError(): ?string {
    return $this->lastError;
  }

}
