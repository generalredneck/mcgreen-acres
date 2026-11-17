<?php

namespace Drupal\Tests\mcgreen_cut_sheet\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the cut sheet form's server-side rules, emails and print page.
 *
 * These run without JavaScript, so they prove the show/hide rules hold on the
 * server and not only in the browser.
 *
 * @group mcgreen_cut_sheet
 */
#[RunTestsInSeparateProcesses]
class CutSheetFormTest extends BrowserTestBase {

  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['webform', 'mcgreen_webform_network_check', 'mcgreen_cut_sheet'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Answers every question a half hog is asked.
   */
  const HALF_HOG = [
    'customer_name' => 'Jane Doe',
    'customer_email' => 'jane@example.com',
    'customer_cell_phone' => '555-0100',
    'hog_type' => 'half',
    'loin_1_cut' => 'bone_in',
    'spare_ribs' => 'keep',
    'shoulder_1_cut' => 'butt_roast',
    'ham_1_cut' => 'cured_whole',
    'bacon_1_cut' => 'fresh',
    'jowl_cut' => 'grind',
    'heart' => 'discard',
    'liver' => 'discard',
    'ground_use' => 'ground_pork',
  ];

  /**
   * A half hog submits without the second set of answers or hidden extras.
   */
  public function testHalfHogNeedsOnlyFirstParts(): void {
    $this->drupalGet('/webform/pig_cut_sheet');
    $this->submitForm(static::HALF_HOG, 'Submit');
    $this->assertSession()->pageTextContains('We received your cut sheet');

    $submissions = \Drupal::entityTypeManager()->getStorage('webform_submission')->loadByProperties(['webform_id' => 'pig_cut_sheet']);
    $this->assertCount(1, $submissions);
    $data = reset($submissions)->getData();
    $this->assertSame('half', $data['hog_type']);
    $this->assertSame('pass', $data['network_check']);
  }

  /**
   * A whole hog must answer for the second loin, unless "same for both".
   */
  public function testWholeHogSecondPartRequiredUnlessSame(): void {
    $whole = ['hog_type' => 'whole'] + static::HALF_HOG;
    $this->drupalGet('/webform/pig_cut_sheet');
    $this->submitForm($whole, 'Submit');
    $this->assertSession()->pageTextContains('Loin 2: cut field is required');
    $this->assertSession()->pageTextNotContains('We received your cut sheet');

    $this->drupalGet('/webform/pig_cut_sheet');
    $this->submitForm($whole + [
      'loins_same' => TRUE,
      'shoulders_same' => TRUE,
      'hams_same' => TRUE,
      'bacon_same' => TRUE,
    ], 'Submit');
    $this->assertSession()->pageTextContains('We received your cut sheet');
  }

  /**
   * Boneless loins ask about the tenderloin and ribs; bone-in loins do not.
   */
  public function testBonelessLoinMustDecideTenderloinAndRibs(): void {
    $this->drupalGet('/webform/pig_cut_sheet');
    $this->submitForm(['loin_1_cut' => 'boneless'] + static::HALF_HOG, 'Submit');
    $this->assertSession()->pageTextContains('Loin 1: tenderloin field is required');
    $this->assertSession()->pageTextContains('Loin 1: baby back ribs field is required');

    $this->drupalGet('/webform/pig_cut_sheet');
    $this->submitForm([
      'loin_1_cut' => 'boneless',
      'loin_1_tenderloin' => 'keep',
      'loin_1_baby_back_ribs' => 'grind',
    ] + static::HALF_HOG, 'Submit');
    $this->assertSession()->pageTextContains('We received your cut sheet');
  }

  /**
   * Smoked sausage, links and raw sausage have a 10 lb minimum; bags do not.
   */
  public function testSausageMinimums(): void {
    $sausage = ['ground_use' => 'sausage'] + static::HALF_HOG;

    foreach (['smoked_sausage', 'fully_cooked_links', 'raw_sausage'] as $type) {
      $this->drupalGet('/webform/pig_cut_sheet');
      $this->submitForm($sausage + ["sausage_types[$type]" => TRUE, "sausage_lb_$type" => '9'], 'Submit');
      $this->assertSession()->pageTextContains('must be higher than or equal to 10');
      $this->assertSession()->pageTextNotContains('We received your cut sheet');
    }

    $this->drupalGet('/webform/pig_cut_sheet');
    $this->submitForm($sausage + [
      'sausage_types[smoked_sausage]' => TRUE,
      'sausage_lb_smoked_sausage' => '10',
      'sausage_types[breakfast_bag]' => TRUE,
      'sausage_lb_breakfast_bag' => '2',
    ], 'Submit');
    $this->assertSession()->pageTextContains('We received your cut sheet');
  }

  /**
   * Staff and customer both get the sheet; hosting-network submissions don't.
   */
  public function testEmailsSendTheSheet(): void {
    $this->drupalGet('/webform/pig_cut_sheet');
    $this->submitForm(static::HALF_HOG, 'Submit');

    $mails = $this->getMails(['module' => 'webform']);
    $this->assertCount(2, $mails);
    $by_to = [];
    foreach ($mails as $mail) {
      $by_to[$mail['to']] = $mail;
    }
    $this->assertArrayHasKey('jane@example.com', $by_to);
    $this->assertArrayHasKey('mcgreenacres@gmail.com', $by_to);
    foreach ($by_to as $mail) {
      // Mail is converted to plain text and wrapped, so compare without
      // line breaks.
      $body = preg_replace('/\s+/', ' ', (string) $mail['body']);
      $this->assertStringContainsString('*Customer:* Jane Doe. Cell phone 555-0100.', $body);
      $this->assertStringContainsString('Please contact the customer directly with any questions', $body);
      $this->assertStringNotContainsString('<p>', $body);
      $this->assertStringNotContainsString('&lt;', $body);
    }
    $this->assertSame('jane@example.com', $by_to['mcgreenacres@gmail.com']['reply-to'] ?? $by_to['mcgreenacres@gmail.com']['headers']['Reply-To']);
  }

  /**
   * The print page is staff-only, bare, and only for the cut sheet webform.
   */
  public function testPrintPage(): void {
    $this->drupalGet('/webform/pig_cut_sheet');
    $this->submitForm(static::HALF_HOG, 'Submit');
    $submission = current(\Drupal::entityTypeManager()->getStorage('webform_submission')->loadByProperties(['webform_id' => 'pig_cut_sheet']));
    $path = '/admin/structure/webform/manage/pig_cut_sheet/submission/' . $submission->id() . '/cut-sheet';

    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['view any webform submission']));
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'no-store');
    $this->assertSession()->responseHeaderEquals('X-Robots-Tag', 'noindex, nofollow');
    $this->assertSession()->responseContains('<!DOCTYPE html>');
    $this->assertSession()->pageTextContains('Customer: Jane Doe.');
    $this->assertSession()->pageTextContains('Hog ID: ______.');
    $this->assertSession()->pageTextContains('Loin: Bone-in chops, processor default size.');
    $this->assertSession()->buttonExists('Print or save as PDF');

    // Another webform's submissions have no cut sheet.
    Webform::create(['id' => 'other', 'title' => 'Other'])->save();
    $other = WebformSubmission::create(['webform_id' => 'other', 'data' => []]);
    $other->save();
    $this->drupalGet('/admin/structure/webform/manage/other/submission/' . $other->id() . '/cut-sheet');
    $this->assertSession()->statusCodeEquals(403);
    // A cut sheet submission under the wrong webform id is refused too.
    $this->drupalGet('/admin/structure/webform/manage/other/submission/' . $submission->id() . '/cut-sheet');
    $this->assertSession()->statusCodeEquals(403);
  }

}
