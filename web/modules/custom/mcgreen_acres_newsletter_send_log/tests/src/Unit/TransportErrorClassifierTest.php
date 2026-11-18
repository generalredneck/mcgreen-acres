<?php

namespace Drupal\Tests\mcgreen_acres_newsletter_send_log\Unit;

use Drupal\mcgreen_acres_newsletter_send_log\TransportErrorClassifier;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the transient-error classifier.
 *
 * @group mcgreen_acres_newsletter_send_log
 * @covers \Drupal\mcgreen_acres_newsletter_send_log\TransportErrorClassifier
 */
class TransportErrorClassifierTest extends UnitTestCase {

  /**
   * Tests classification of representative messages.
   *
   * @dataProvider messageProvider
   */
  public function testClassify(string $message, string $expected): void {
    $this->assertSame($expected, (new TransportErrorClassifier())->classify($message));
  }

  /**
   * Messages and their expected classification.
   */
  public static function messageProvider(): array {
    $t = TransportErrorClassifier::TRANSIENT;
    $o = TransportErrorClassifier::OTHER;
    return [
      'dropped connection' => ['Connection to "smtp.hostinger.com:587" has been closed unexpectedly.', $t],
      'connect failure' => ['Connection could not be established with host "smtp.example.com:587": stream_socket_client(): Unable to connect', $t],
      'timeout' => ['Connection to "smtp.example.com:587" timed out.', $t],
      'smtp 421' => ['Expected response code "250" but got code "421", with message "421 4.7.0 Try again later".', $t],
      'temporary' => ['Temporary failure in name resolution', $t],
      'daily limit is a 5xx' => ['Expected response code "250" but got code "550", with message "550 Daily user sending limit exceeded".', $o],
      'bad mailbox' => ['Expected "RCPT TO" but got code "550", with message "550 No such user".', $o],
      'missing transport' => ['Missing email transport: please configure a default.', $o],
      'empty' => ['', $o],
    ];
  }

}
