<?php

namespace Drupal\Tests\mcgreen_acres_newsletter_segments\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simplenews\SubscriberInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests the "Subscribers by tag" recipient handler.
 *
 * @group mcgreen_acres_newsletter_segments
 */
class RecipientHandlerTagsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'taxonomy',
    'simplenews',
    'entity_test',
    'mcgreen_acres_newsletter_segments',
  ];

  /**
   * Term ID for the "HerdShareWaitList" tag.
   *
   * @var int
   */
  protected $waitlistTid;

  /**
   * Term ID for the "VIP" tag.
   *
   * @var int
   */
  protected $vipTid;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('simplenews_subscriber');
    $this->installEntitySchema('simplenews_subscriber_history');
    $this->installEntitySchema('entity_test');
    $this->installSchema('simplenews', ['simplenews_mail_spool']);

    Vocabulary::create(['vid' => 'subscriber_tags', 'name' => 'Subscriber Tags'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'simplenews_subscriber',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'simplenews_subscriber',
      'bundle' => 'simplenews_subscriber',
    ])->save();

    foreach (['field_send_to_tags', 'field_exclude_tags'] as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'entity_test',
        'type' => 'entity_reference',
        'settings' => ['target_type' => 'taxonomy_term'],
        'cardinality' => -1,
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'entity_test',
        'bundle' => 'entity_test',
      ])->save();
    }

    Newsletter::create(['id' => 'default', 'name' => 'Default'])->save();

    $waitlist_term = Term::create(['vid' => 'subscriber_tags', 'name' => 'HerdShareWaitList']);
    $waitlist_term->save();
    $this->waitlistTid = (int) $waitlist_term->id();

    $vip_term = Term::create(['vid' => 'subscriber_tags', 'name' => 'VIP']);
    $vip_term->save();
    $this->vipTid = (int) $vip_term->id();
  }

  /**
   * Creates a subscriber with the given tags, subscribed to 'default'.
   */
  protected function createSubscriber(string $mail, array $tag_ids, int $status = SubscriberInterface::ACTIVE): Subscriber {
    $subscriber = Subscriber::create([
      'mail' => $mail,
      'status' => $status,
      'subscriptions' => ['default'],
      'field_tags' => $tag_ids,
    ]);
    $subscriber->save();
    return $subscriber;
  }

  /**
   * Builds a recipient handler for a fake issue with the given tag fields.
   */
  protected function getHandler(array $send_to_tids = [], array $exclude_tids = []) {
    $issue = EntityTest::create([
      'name' => 'Test issue',
      'field_send_to_tags' => $send_to_tids,
      'field_exclude_tags' => $exclude_tids,
    ]);
    $issue->save();

    $manager = \Drupal::service('plugin.manager.simplenews_recipient_handler');
    return $manager->createInstance('simplenews_tags', [
      '_issue' => $issue,
      '_newsletter_ids' => ['default'],
    ]);
  }

  /**
   * Empty send-to/exclude behaves like "send to all subscribers".
   */
  public function testNoTagsSendsToAllActiveSubscribers() {
    $this->createSubscriber('a@example.com', [$this->waitlistTid]);
    $this->createSubscriber('b@example.com', [$this->vipTid]);
    $this->createSubscriber('c@example.com', [], SubscriberInterface::INACTIVE);

    $handler = $this->getHandler();
    $this->assertEquals(2, $handler->count());
  }

  /**
   * Send-to tags restrict recipients to subscribers with a matching tag.
   */
  public function testSendToTagsFiltersRecipients() {
    $this->createSubscriber('a@example.com', [$this->waitlistTid]);
    $this->createSubscriber('b@example.com', [$this->vipTid]);
    $this->createSubscriber('c@example.com', [$this->waitlistTid, $this->vipTid]);

    $handler = $this->getHandler([$this->waitlistTid]);
    $this->assertEquals(2, $handler->count());
  }

  /**
   * A subscriber with multiple matching tags is only counted once.
   */
  public function testSendToTagsDoesNotDuplicateRecipients() {
    $this->createSubscriber('a@example.com', [$this->waitlistTid, $this->vipTid]);

    $handler = $this->getHandler([$this->waitlistTid, $this->vipTid]);
    $this->assertEquals(1, $handler->count());
  }

  /**
   * Exclude tags remove matching subscribers even if send-to matched too.
   */
  public function testExcludeTagsOverrideSendToTags() {
    $this->createSubscriber('a@example.com', [$this->waitlistTid]);
    $this->createSubscriber('b@example.com', [$this->waitlistTid, $this->vipTid]);

    $handler = $this->getHandler([$this->waitlistTid], [$this->vipTid]);
    $this->assertEquals(1, $handler->count());
  }

  /**
   * Exclude tags apply even without a send-to list.
   */
  public function testExcludeTagsWithoutSendToTags() {
    $this->createSubscriber('a@example.com', [$this->waitlistTid]);
    $this->createSubscriber('b@example.com', [$this->vipTid]);

    $handler = $this->getHandler([], [$this->vipTid]);
    $this->assertEquals(1, $handler->count());
  }

  /**
   * Inactive subscribers are never selected, even if tagged.
   */
  public function testInactiveSubscribersExcluded() {
    $this->createSubscriber('a@example.com', [$this->waitlistTid], SubscriberInterface::INACTIVE);

    $handler = $this->getHandler([$this->waitlistTid]);
    $this->assertEquals(0, $handler->count());
  }

}
