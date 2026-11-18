<?php

namespace Drupal\Tests\mcgreen_acres_newsletter_send_log\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplenews\AbortSendingException;
use Drupal\simplenews\Mail\MailInterface;
use Drupal\simplenews\Spool\SpoolStorageInterface;
use Drupal\simplenews\SubscriberInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests retry, abort and give-up decisions of the retrying mailer.
 *
 * @group mcgreen_acres_newsletter_send_log
 * @covers \Drupal\mcgreen_acres_newsletter_send_log\RetryingMailer
 * @covers \Drupal\mcgreen_acres_newsletter_send_log\SendAttemptStorage
 */
#[RunTestsInSeparateProcesses]
class RetryingMailerTest extends KernelTestBase {

  const DROPPED = 'Connection to "smtp.hostinger.com:587" has been closed unexpectedly.';

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
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->getDefinition('plugin.manager.mail')->setClass(FakeMailManager::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'mcgreen_acres_newsletter_send_log']);
    $this->installSchema('simplenews', ['simplenews_mail_spool']);
    $this->installSchema('mcgreen_acres_newsletter_send_log', ['mcgreen_newsletter_send_attempt']);
    // No real sleeping between retries.
    $this->config('mcgreen_acres_newsletter_send_log.settings')->set('retry_delay', 0)->save();
  }

  /**
   * Builds a newsletter mail for the given recipient.
   */
  protected function mail(string $address = 'a@example.com'): MailInterface {
    $issue = $this->createMock(ContentEntityInterface::class);
    $issue->method('getEntityTypeId')->willReturn('node');
    $issue->method('id')->willReturn(5);
    $subscriber = $this->createMock(SubscriberInterface::class);
    $subscriber->method('id')->willReturn(7);

    $mail = $this->createMock(MailInterface::class);
    $mail->method('getKey')->willReturn('node');
    $mail->method('getRecipient')->willReturn($address);
    $mail->method('getLanguage')->willReturn('en');
    $mail->method('getFromFormatted')->willReturn('Test <test@example.com>');
    $mail->method('getIssue')->willReturn($issue);
    $mail->method('getSubscriber')->willReturn($subscriber);
    return $mail;
  }

  /**
   * Scripts the fake mail manager.
   */
  protected function script(array $results): FakeMailManager {
    $manager = $this->container->get('plugin.manager.mail');
    $manager->script = $results;
    return $manager;
  }

  /**
   * Returns logged attempts as [attempt, outcome, classification].
   */
  protected function logged(): array {
    $rows = $this->container->get('mcgreen_acres_newsletter_send_log.storage')->loadForIssue('node', 5);
    return array_map(fn($r) => [(int) $r->attempt, $r->outcome, $r->classification], $rows);
  }

  /**
   * A dropped connection is retried immediately and the recipient gets mail.
   */
  public function testDroppedConnectionRecoversOnImmediateRetry(): void {
    $this->script([self::DROPPED, TRUE]);
    $result = $this->container->get('simplenews.mailer')->sendMail($this->mail());

    $this->assertSame(SpoolStorageInterface::STATUS_DONE, $result);
    $this->assertSame([[1, 'error', 'transient'], [2, 'sent', NULL]], $this->logged());
  }

  /**
   * With nothing sent yet, repeated drops abort the batch instead of failing.
   */
  public function testRepeatedDropsWithNothingSentAbortTheBatch(): void {
    $this->script([self::DROPPED, self::DROPPED, self::DROPPED]);
    try {
      $this->container->get('simplenews.mailer')->sendMail($this->mail());
      $this->fail('Expected the batch to be aborted.');
    }
    catch (AbortSendingException) {
    }
    $this->assertSame([[1, 'error', 'transient'], [2, 'error', 'transient'], [3, 'error', 'transient']], $this->logged());
  }

  /**
   * Once others have been sent, a persistent failure is recipient-specific.
   */
  public function testPersistentFailureAfterASuccessIsLeftToSimplenews(): void {
    $this->script([TRUE, self::DROPPED, self::DROPPED, self::DROPPED]);
    $mailer = $this->container->get('simplenews.mailer');

    $this->assertSame(SpoolStorageInterface::STATUS_DONE, $mailer->sendMail($this->mail('first@example.com')));
    $this->assertSame(SpoolStorageInterface::STATUS_PENDING, $mailer->sendMail($this->mail('second@example.com')));
    $this->assertCount(3, $this->logged());
  }

  /**
   * A recipient that keeps failing across cron runs is eventually given up on.
   */
  public function testGivesUpAfterMaxTotalAttempts(): void {
    $storage = $this->container->get('mcgreen_acres_newsletter_send_log.storage');
    for ($i = 1; $i <= 5; $i++) {
      $storage->record('node', 5, 7, 'a@example.com', $i, 'error', 'transient', self::DROPPED);
    }
    // Only one send is scripted: no further immediate retry may happen.
    $this->script([self::DROPPED]);
    $result = $this->container->get('simplenews.mailer')->sendMail($this->mail());

    $this->assertSame(SpoolStorageInterface::STATUS_FAILED, $result);
    $this->assertSame(6, $storage->countErrors('node', 5, 'a@example.com'));
  }

  /**
   * Errors that are not transport problems are not retried.
   */
  public function testUnrecognizedErrorIsNotRetried(): void {
    $this->script(['Expected response code "250" but got code "550", with message "550 No such user".']);
    $result = $this->container->get('simplenews.mailer')->sendMail($this->mail());

    $this->assertSame(SpoolStorageInterface::STATUS_PENDING, $result);
    $this->assertSame([[1, 'error', 'other']], $this->logged());
  }

  /**
   * Old rows are purged and others kept.
   */
  public function testPurge(): void {
    $storage = $this->container->get('mcgreen_acres_newsletter_send_log.storage');
    $storage->record('node', 5, 7, 'a@example.com', 1, 'error', 'transient', 'x');
    $this->assertSame(0, $storage->purge(30));
    $this->assertCount(1, $this->logged());
    $this->assertSame(1, $storage->purge(-1));
    $this->assertCount(0, $this->logged());
  }

}
