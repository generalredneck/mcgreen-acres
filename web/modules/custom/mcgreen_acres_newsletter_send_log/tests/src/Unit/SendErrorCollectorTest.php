<?php

namespace Drupal\Tests\mcgreen_acres_newsletter_send_log\Unit;

use Drupal\mcgreen_acres_newsletter_send_log\SendErrorCollector;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that only Symfony Mailer send errors are captured.
 *
 * @group mcgreen_acres_newsletter_send_log
 * @covers \Drupal\mcgreen_acres_newsletter_send_log\SendErrorCollector
 */
class SendErrorCollectorTest extends UnitTestCase {

  /**
   * Tests capture, reset, and filtering by channel and message.
   */
  public function testCapture(): void {
    $collector = new SendErrorCollector();
    $this->assertNull($collector->getLastError());

    $collector->error('Something else', ['channel' => 'symfony_mailer', '%message' => 'ignored']);
    $collector->error('Error sending email: %message', ['channel' => 'cron', '%message' => 'ignored']);
    $this->assertNull($collector->getLastError());

    $collector->error('Error sending email: %message', ['channel' => 'symfony_mailer', '%message' => 'closed unexpectedly']);
    $this->assertSame('closed unexpectedly', $collector->getLastError());

    $collector->reset();
    $this->assertNull($collector->getLastError());
  }

}
