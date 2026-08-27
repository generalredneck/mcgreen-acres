<?php

namespace Drupal\Tests\mcgreen_acres_newsletter_history\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simplenews\SubscriberInterface;

/**
 * Tests the subscriber history builder against real recorded history.
 *
 * @group mcgreen_acres_newsletter_history
 * @covers \Drupal\mcgreen_acres_newsletter_history\SubscriberHistoryBuilder
 */
class SubscriberHistoryBuilderTest extends KernelTestBase {

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
    'mcgreen_acres_newsletter_history',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('simplenews_subscriber');
    $this->installEntitySchema('simplenews_subscriber_history');
    $this->installSchema('simplenews', ['simplenews_mail_spool']);

    Newsletter::create(['id' => 'default', 'name' => 'Default'])->save();
    Newsletter::create(['id' => 'weekly', 'name' => 'Weekly'])->save();
  }

  /**
   * The builder service under test.
   */
  protected function builder() {
    return $this->container->get('mcgreen_acres_newsletter_history.builder');
  }

  /**
   * An unconfirmed subscriber has no history, so the empty message shows.
   */
  public function testNoHistoryShowsEmptyMessage(): void {
    $subscriber = Subscriber::create([
      'mail' => 'nobody@example.com',
      'status' => SubscriberInterface::UNCONFIRMED,
    ]);
    $subscriber->save();

    $build = $this->builder()->build($subscriber);

    $this->assertArrayHasKey('empty', $build);
    $this->assertArrayNotHasKey('table', $build);
  }

  /**
   * Subscribe, add a second newsletter, then unsubscribe: three ordered rows.
   */
  public function testSubscribeAddUnsubscribeAreOrderedNewestFirst(): void {
    $subscriber = Subscriber::create([
      'mail' => 'a@example.com',
      'status' => SubscriberInterface::ACTIVE,
      'subscriptions' => ['default'],
    ]);
    $subscriber->save();

    $subscriber->subscribe('weekly');
    $subscriber->save();

    $subscriber->unsubscribe('default');
    $subscriber->save();

    $rows = $this->builder()->build($subscriber)['table']['#rows'];
    $this->assertCount(3, $rows);

    // Newest first: unsubscribe, then add weekly, then initial subscribe.
    $this->assertSame('Unsubscribed from Default', (string) $rows[0]['data']['event']);
    $this->assertSame('Weekly', (string) $rows[0]['data']['newsletters']);

    $this->assertSame('Subscribed to Weekly', (string) $rows[1]['data']['event']);
    $this->assertSame('Default, Weekly', (string) $rows[1]['data']['newsletters']);

    $this->assertSame('Subscribed to Default', (string) $rows[2]['data']['event']);
  }

  /**
   * Removing the last newsletter reads as "all newsletters".
   */
  public function testUnsubscribeFromLastNewsletter(): void {
    $subscriber = Subscriber::create([
      'mail' => 'b@example.com',
      'status' => SubscriberInterface::ACTIVE,
      'subscriptions' => ['default'],
    ]);
    $subscriber->save();

    $subscriber->unsubscribe('default');
    $subscriber->save();

    $rows = $this->builder()->build($subscriber)['table']['#rows'];
    $this->assertSame('Unsubscribed from all newsletters', (string) $rows[0]['data']['event']);
    $this->assertSame('None', (string) $rows[0]['data']['newsletters']);
  }

  /**
   * A CLI/kernel save records an empty route, shown as "Programmatic".
   */
  public function testProgrammaticSourceLabel(): void {
    $subscriber = Subscriber::create([
      'mail' => 'c@example.com',
      'status' => SubscriberInterface::ACTIVE,
      'subscriptions' => ['default'],
    ]);
    $subscriber->save();

    $source = (string) $this->builder()->build($subscriber)['table']['#rows'][0]['data']['source'];
    $this->assertStringContainsString('Programmatic', $source);
  }

  /**
   * History is matched by email, and follows the subscriber's current address.
   */
  public function testHistoryIsScopedToSubscriberEmail(): void {
    $other = Subscriber::create([
      'mail' => 'other@example.com',
      'status' => SubscriberInterface::ACTIVE,
      'subscriptions' => ['weekly'],
    ]);
    $other->save();

    $subscriber = Subscriber::create([
      'mail' => 'mine@example.com',
      'status' => SubscriberInterface::ACTIVE,
      'subscriptions' => ['default'],
    ]);
    $subscriber->save();

    $rows = $this->builder()->build($subscriber)['table']['#rows'];
    $this->assertCount(1, $rows);
    $this->assertSame('Subscribed to Default', (string) $rows[0]['data']['event']);
  }

  /**
   * A later record with no newsletter diff (re-add, email/language change) is
   * labelled rather than dropped — this is the shape of a re-subscribe after the
   * subscriber entity was deleted and recreated.
   */
  public function testNoNewsletterChangeIsLabelled(): void {
    $subscriber = Subscriber::create([
      'mail' => 'd@example.com',
      'status' => SubscriberInterface::ACTIVE,
      'subscriptions' => ['default'],
    ]);
    $subscriber->save();

    // A second history row, later, with the identical newsletter set.
    $this->container->get('entity_type.manager')
      ->getStorage('simplenews_subscriber_history')
      ->create([
        'mail' => 'd@example.com',
        'timestamp' => \Drupal::time()->getRequestTime() + 3600,
        'uid' => 0,
        'source' => 'route:',
        'subscriptions' => ['default'],
      ])->save();

    $rows = $this->builder()->build($subscriber)['table']['#rows'];
    $this->assertCount(2, $rows);
    $this->assertSame(
      'Re-subscribed or details updated (no newsletter change)',
      (string) $rows[0]['data']['event']
    );
    $this->assertSame('Subscribed to Default', (string) $rows[1]['data']['event']);
  }

}
