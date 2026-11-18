<?php

namespace Drupal\Tests\mcgreen_acres_newsletter_send_log\Kernel;

use Drupal\Core\Mail\MailManager;

/**
 * Mail manager that replays scripted results instead of sending.
 *
 * Each script entry is TRUE for a successful send, or an error string. Errors
 * are logged exactly as Symfony Mailer logs them, so the error collector is
 * exercised for real.
 */
class FakeMailManager extends MailManager {

  /**
   * Results to replay, in order.
   *
   * @var array<int, true|string>
   */
  public array $script = [];

  /**
   * Number of sends attempted.
   */
  public int $calls = 0;

  /**
   * {@inheritdoc}
   */
  public function mail($module, $key, $to, $langcode, $params = [], $reply = NULL, $send = TRUE) {
    $this->calls++;
    $next = array_shift($this->script);
    if ($next === NULL) {
      throw new \LogicException('Mail sent more often than the test scripted.');
    }
    if ($next !== TRUE) {
      \Drupal::service('logger.factory')->get('symfony_mailer')->error('Error sending email: %message', ['%message' => $next]);
    }
    return ['result' => $next === TRUE, 'to' => $to, 'subject' => 'Test'];
  }

}
