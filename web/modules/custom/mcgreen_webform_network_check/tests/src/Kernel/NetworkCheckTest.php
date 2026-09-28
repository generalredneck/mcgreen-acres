<?php

namespace Drupal\Tests\mcgreen_webform_network_check\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcgreen_webform_network_check\AsnLookup;
use Drupal\mcgreen_webform_network_check\QuarantineReporter;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Psr\Log\NullLogger;

/**
 * Tests network classification, handler gating, digest and purge.
 *
 * @group mcgreen_webform_network_check
 */
class NetworkCheckTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'path',
    'path_alias',
    'field',
    'webform',
    'mcgreen_webform_network_check',
  ];

  /**
   * Fake networks by IP.
   */
  const NETWORKS = [
    '198.51.100.1' => ['asn' => 55286, 'org' => 'SERVER-MANIA'],
    '198.51.100.2' => ['asn' => 7018, 'org' => 'ATT-INTERNET4'],
    '198.51.100.3' => ['asn' => 54113, 'org' => 'FASTLY'],
    '198.51.100.4' => ['asn' => 64500, 'org' => 'Acme VPS Hosting LLC'],
  ];

  /**
   * The test webform.
   *
   * @var \Drupal\webform\WebformInterface
   */
  protected WebformInterface $webform;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('user');
    $this->installSchema('webform', ['webform']);
    $this->installConfig(['system', 'webform', 'mcgreen_webform_network_check']);
    $this->installEntitySchema('webform_submission');
    $this->config('system.site')->set('mail', 'staff@example.com')->save();
    $this->config('system.mail')->set('interface.default', 'test_mail_collector')->save();

    $this->container->set('mcgreen_webform_network_check.asn_lookup', new class($this->container->get('config.factory'), new NullLogger(), $this->root) extends AsnLookup {

      /**
       * {@inheritdoc}
       */
      public function lookup(string $ip): ?array {
        return NetworkCheckTest::NETWORKS[$ip] ?? NULL;
      }

    });

    $handlers = \Drupal::service('plugin.manager.webform.handler');
    $this->webform = Webform::create(['id' => 'network_test', 'title' => 'Network test']);
    $this->webform->setElements([
      'first_name' => ['#type' => 'textfield', '#title' => 'First name'],
      'email' => ['#type' => 'email', '#title' => 'Email'],
      'network_check' => ['#type' => 'value', '#title' => 'Network check', '#value' => ''],
    ]);
    $this->webform->addWebformHandler($handlers->createInstance('network_check', [
      'handler_id' => 'network_check',
      'weight' => -50,
    ]));
    $this->webform->addWebformHandler($handlers->createInstance('email', [
      'handler_id' => 'welcome',
      'conditions' => [
        'enabled' => [
          ':input[name="network_check"]' => ['!value' => 'hosting'],
        ],
      ],
      'settings' => [
        'states' => [WebformSubmissionInterface::STATE_COMPLETED],
        'to_mail' => '[webform_submission:values:email:raw]',
        'subject' => 'Welcome',
        'body' => 'Hey there',
        'html' => FALSE,
      ],
    ]));
    $this->webform->save();
  }

  /**
   * Creates a completed submission from an IP.
   */
  protected function submit(string $ip, string $email, ?int $created = NULL): WebformSubmissionInterface {
    $submission = WebformSubmission::create([
      'webform_id' => $this->webform->id(),
      'remote_addr' => $ip,
      'data' => ['first_name' => 'Pat', 'email' => $email],
    ]);
    if ($created) {
      $submission->setCreatedTime($created);
    }
    $submission->save();
    return $submission;
  }

  /**
   * Returns the addresses the welcome email was sent to.
   */
  protected function welcomeRecipients(): array {
    $mails = \Drupal::state()->get('system.test_mail_collector', []);
    return array_values(array_map(fn($m) => $m['to'], array_filter($mails, fn($m) => $m['subject'] === 'Welcome')));
  }

  /**
   * Hosting networks are flagged and skip gated handlers; others don't.
   */
  public function testClassificationGatesHandlers(): void {
    $cases = [
      '198.51.100.1' => ['hosting', 'listed@example.com'],
      '198.51.100.2' => ['pass', 'isp@example.com'],
      '198.51.100.3' => ['relay', 'relay@example.com'],
      '198.51.100.4' => ['hosting', 'keyword@example.com'],
      '203.0.113.9' => ['pass', 'unknown@example.com'],
    ];
    $submissions = [];
    foreach ($cases as $ip => [$expected, $email]) {
      $submissions[$ip] = $this->submit($ip, $email);
      $this->assertSame($expected, $submissions[$ip]->getElementData('network_check'), $ip);
    }

    $this->assertEqualsCanonicalizing(
      ['isp@example.com', 'relay@example.com', 'unknown@example.com'],
      $this->welcomeRecipients(),
    );

    $hosting = $submissions['198.51.100.1'];
    $this->assertStringContainsString('AS55286 SERVER-MANIA (hosting ASN list)', $hosting->getNotes());
    $this->assertStringContainsString('keyword "hosting"', $submissions['198.51.100.4']->getNotes());
    $this->assertSame('', (string) $submissions['198.51.100.2']->getNotes());

    // A staff edit neither re-classifies nor duplicates the note.
    $notes = $hosting->getNotes();
    $hosting->save();
    $this->assertSame('hosting', $hosting->getElementData('network_check'));
    $this->assertSame($notes, $hosting->getNotes());
  }

  /**
   * The database path is configurable, relative to the Drupal root or not.
   */
  public function testDatabasePath(): void {
    $lookup = new AsnLookup($this->container->get('config.factory'), new NullLogger(), '/srv/web');
    $config = $this->config('mcgreen_webform_network_check.settings');

    $this->assertSame('/srv/web/../private/geoip2/GeoLite2-ASN.mmdb', $lookup->getDatabasePath(), 'Shipped default is relative to the Drupal root.');
    $config->set('asn_database', '/data/GeoLite2-ASN.mmdb')->save();
    $this->assertSame('/data/GeoLite2-ASN.mmdb', $lookup->getDatabasePath());
    $config->set('asn_database', '')->save();
    $this->assertNull($lookup->getDatabasePath());

    // A missing database fails open: unknown network, submission passes.
    $config->set('asn_database', '/nonexistent/GeoLite2-ASN.mmdb')->save();
    $missing = new AsnLookup($this->container->get('config.factory'), new NullLogger(), '/srv/web');
    $this->assertNull($missing->lookup('23.236.152.124'));
  }

  /**
   * The digest reports new quarantines once per interval; purge is gated.
   */
  public function testDigestAndPurge(): void {
    /** @var \Drupal\mcgreen_webform_network_check\QuarantineReporter $reporter */
    $reporter = \Drupal::service('mcgreen_webform_network_check.reporter');
    $now = \Drupal::time()->getRequestTime();

    $old = $this->submit('198.51.100.1', 'victim1@example.com', $now - 20 * 86400);
    $this->submit('198.51.100.4', 'victim2@example.com', $now - 3600);
    $this->submit('198.51.100.2', 'real@example.com', $now - 1800);

    $this->assertSame(2, $reporter->sendDigest($now));
    $digests = array_values(array_filter(
      \Drupal::state()->get('system.test_mail_collector', []),
      fn($m) => $m['id'] === 'mcgreen_webform_network_check_digest',
    ));
    $this->assertCount(1, $digests);
    $this->assertSame('staff@example.com', $digests[0]['to']);
    $this->assertStringContainsString('2 form submissions quarantined', $digests[0]['subject']);
    $body = (string) $digests[0]['body'];
    $this->assertStringContainsString('victim1@example.com', $body);
    $this->assertStringContainsString('AS55286 SERVER-MANIA', $body);
    $this->assertStringNotContainsString('real@example.com', $body);

    // Within the interval: nothing sent, even with a new quarantine.
    $late = $this->submit('198.51.100.1', 'victim3@example.com', $now + 60);
    $this->assertSame(0, $reporter->sendDigest($now + 120));

    // Purge after 14 days only removes reported submissions past retention.
    $this->assertSame(1, $reporter->purge($now));
    $storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
    $storage->resetCache();
    $this->assertNull($storage->load($old->id()));
    // Much later, the reported one goes but the unreported one is kept.
    $this->assertSame(1, $reporter->purge($now + 30 * 86400));
    $this->assertNotNull($storage->load($late->id()), 'Unreported submission is kept.');

    // Next interval: only the new one is reported, then it can be purged.
    $later = $now + 86400 + 1;
    $this->assertSame(1, $reporter->sendDigest($later));
    $this->assertSame(0, $reporter->sendDigest($later + 86400 + 1), 'Nothing new: no email.');
    $this->assertSame(1, $reporter->purge($later + 30 * 86400));
    $this->assertSame((int) $late->getCreatedTime(), \Drupal::state()->get(QuarantineReporter::STATE_REPORTED_THROUGH));
  }

}
