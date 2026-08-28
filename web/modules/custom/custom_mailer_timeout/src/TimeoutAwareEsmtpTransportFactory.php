<?php

namespace Drupal\custom_mailer_timeout;

use Drupal\Core\Site\Settings;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * ESMTP transport factory that enforces a short connection timeout.
 *
 * Symfony Mailer's SmtpTransport has no DSN option for the socket connect
 * timeout; it always falls back to PHP's default_socket_timeout (commonly
 * 60s). A single unresponsive SMTP server can then burn through the whole
 * cron request's max_execution_time before anything is caught, silently
 * stranding the rest of a mail batch. Cutting the timeout down to a few
 * seconds makes a bad connection fail fast instead.
 *
 * EsmtpTransportFactory is final, so this wraps rather than extends it.
 */
final class TimeoutAwareEsmtpTransportFactory extends AbstractTransportFactory {

  /**
   * Default connection timeout in seconds, if not set via Settings.
   */
  const DEFAULT_TIMEOUT = 10;

  /**
   * {@inheritdoc}
   */
  public function create(Dsn $dsn): TransportInterface {
    $inner = new EsmtpTransportFactory($this->dispatcher, $this->client, $this->logger);
    $transport = $inner->create($dsn);

    if ($transport instanceof SmtpTransport) {
      $stream = $transport->getStream();
      if ($stream instanceof SocketStream) {
        $stream->setTimeout((float) Settings::get('mailer_smtp_timeout', self::DEFAULT_TIMEOUT));
      }
    }

    return $transport;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSupportedSchemes(): array {
    return ['smtp', 'smtps'];
  }

}
