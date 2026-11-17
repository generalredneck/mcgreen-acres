<?php

namespace Drupal\Tests\mcgreen_cut_sheet\Kernel;

use Drupal\Core\Render\Markup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the plain-English cut sheet and its email token.
 *
 * @group mcgreen_cut_sheet
 */
#[RunTestsInSeparateProcesses]
class CutSheetNarrativeTest extends KernelTestBase {

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
    'mcgreen_cut_sheet',
  ];

  /**
   * A whole hog's answers, every part chosen.
   */
  const WHOLE = [
    'customer_name' => 'Jane Doe',
    'customer_cell_phone' => '555-0100',
    'customer_backup_phone' => '555-0101',
    'hog_type' => 'whole',
    'slaughter_date' => '2026-12-02',
    'loin_1_cut' => 'bone_in',
    'loin_1_chop_size' => '1 inch thick, 2 per package',
    'loin_2_cut' => 'boneless',
    'loin_2_chop_size' => '',
    'loin_2_tenderloin' => 'keep',
    'loin_2_baby_back_ribs' => 'keep',
    'spare_ribs' => 'keep',
    'shoulder_1_cut' => 'pork_steaks',
    'shoulder_2_cut' => 'butt_roast',
    'shoulder_2_size' => '3 lb',
    'ham_1_cut' => 'fresh_steaks',
    'ham_1_size' => '3/4 inch thick, 2 per package',
    'ham_2_cut' => 'cured_steaks',
    'bacon_1_cut' => 'cured',
    'bacon_2_cut' => 'cured_peppered',
    'jowl_cut' => 'cured_sliced',
    'heart' => 'keep_whole',
    'liver' => 'keep_sliced',
    'ground_use' => 'sausage',
    'sausage_types' => ['breakfast_bag'],
    'sausage_lb_breakfast_bag' => '20',
    'special_instructions' => '',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('user');
    $this->installSchema('webform', ['webform']);
    $this->installConfig(['system', 'webform', 'mcgreen_cut_sheet']);
    $this->installEntitySchema('webform_submission');
  }

  /**
   * Creates a submission with the given answers laid over a whole hog.
   */
  protected function submission(array $overrides = []): WebformSubmission {
    $submission = WebformSubmission::create([
      'webform_id' => 'pig_cut_sheet',
      'data' => $overrides + static::WHOLE,
    ]);
    $submission->save();
    return $submission;
  }

  /**
   * Builds the plain-text sheet for a submission.
   */
  protected function sheet(WebformSubmission $submission): string {
    $narrative = $this->container->get('mcgreen_cut_sheet.narrative');
    return $narrative->toText($narrative->build($submission));
  }

  /**
   * Option lists ship with the module, one per choice the form asks for.
   */
  public function testOptionListsAreConfig(): void {
    $ids = \Drupal::entityTypeManager()->getStorage('webform_options')->getQuery()->accessCheck(FALSE)->execute();
    $lists = [
      'loin_cut',
      'keep_or_grind',
      'spare_ribs',
      'shoulder_cut',
      'ham_cut',
      'bacon_cut',
      'jowl_cut',
      'heart',
      'liver',
      'ground_use',
      'sausage_type',
    ];
    foreach ($lists as $name) {
      $this->assertArrayHasKey('cut_sheet_' . $name, $ids);
    }
    $this->assertNotNull(Webform::load('pig_cut_sheet'));
  }

  /**
   * A whole hog reads as one paragraph per section, nothing abbreviated.
   */
  public function testWholeHog(): void {
    $expected = <<<'TEXT'
Customer: Jane Doe. Cell phone 555-0100, backup phone 555-0101. Whole hog. Hog ID: ______. Slaughter date: December 2.

Loins: Loin 1, bone-in chops, 1 inch thick, 2 per package. Loin 2, boneless chops, processor default size. Keep the tenderloin and baby back ribs from loin 2.

Spare ribs: Save the ribs.

Shoulders: Shoulder 1, pork steaks, processor default size. Shoulder 2, butt roast, 3 lb.

Hams: Ham 1, fresh ham steaks, 3/4 inch thick, 2 per package. Ham 2, smoke and cure ham steaks, processor default size.

Bacon: Slab 1, smoke and cure bacon, processor default slice. Slab 2, smoke and cure peppered bacon, processor default slice.

Jowls: Smoke and cure, sliced.

Heart and liver: Heart: Keep whole. Liver: Keep sliced.

Ground meat: Make sausage from the ground meat. Breakfast sausage in bags, about 20 lb.

Special instructions: None.

Please contact the customer directly with any questions about these instructions.
TEXT;
    $this->assertSame($expected, $this->sheet($this->submission()));
  }

  /**
   * Checking "same for both" repeats the first part's answers.
   */
  public function testSameForBothRepeatsFirstPart(): void {
    $text = $this->sheet($this->submission([
      'loins_same' => 1,
      'loin_1_cut' => 'boneless',
      'loin_1_tenderloin' => 'keep',
      'loin_1_baby_back_ribs' => 'grind',
      // Stale answers for the hidden second loin must be ignored.
      'loin_2_cut' => 'bone_in',
      'hams_same' => 1,
      'ham_2_cut' => 'cured_whole',
    ]));
    $this->assertStringContainsString('Loin 1, boneless chops, 1 inch thick, 2 per package. Keep the tenderloin from loin 1. Grind the baby back ribs from loin 1 into ground meat. Loin 2, boneless chops, 1 inch thick, 2 per package. Keep the tenderloin from loin 2. Grind the baby back ribs from loin 2 into ground meat.', $text);
    $this->assertStringContainsString('Ham 1, fresh ham steaks, 3/4 inch thick, 2 per package. Ham 2, fresh ham steaks, 3/4 inch thick, 2 per package.', $text);
  }

  /**
   * A half hog has one of each part, no numbers, and names the other owner.
   */
  public function testHalfHog(): void {
    $text = $this->sheet($this->submission([
      'hog_type' => 'half',
      'other_half_owner' => 'Bob Smith',
      'customer_backup_phone' => '',
      'ham_1_cut' => 'cured_whole',
      'ground_use' => 'ground_pork',
      'special_instructions' => "Extra lean.\nThanks",
    ]));
    $this->assertStringContainsString('Cell phone 555-0100. No backup phone. Half hog. Other half\'s owner: Bob Smith. Hog ID: ______.', $text);
    $this->assertStringContainsString('Loin: Bone-in chops, 1 inch thick, 2 per package.', $text);
    $this->assertStringNotContainsString('Loin 2', $text);
    $this->assertStringContainsString('Ham: Smoke and cure whole ham.', $text);
    $this->assertStringNotContainsString('Ham 2', $text);
    $this->assertStringContainsString('Ground meat: All ground meat to ground pork in 1 lb bags.', $text);
    $this->assertStringContainsString("Special instructions: Extra lean.\nThanks", $text);
  }

  /**
   * Only steaks carry a size; other hams and unchosen parts read cleanly.
   */
  public function testHamSizeOnlyForSteaksAndSausageMinimumTypes(): void {
    $text = $this->sheet($this->submission([
      'ham_1_cut' => 'fresh_cutlets',
      'ham_1_size' => 'ignored stale answer',
      'ground_use' => 'sausage',
      'sausage_types' => ['smoked_sausage', 'raw_sausage'],
      'sausage_lb_smoked_sausage' => '10',
      'sausage_lb_raw_sausage' => '12.5',
    ]));
    $this->assertStringContainsString('Ham 1, fresh cutlets. Ham 2', $text);
    $this->assertStringNotContainsString('ignored stale answer', $text);
    $this->assertStringContainsString('Smoked sausage, about 10 lb. Raw sausage, about 12.5 lb.', $text);
  }

  /**
   * Submitted text is escaped in HTML, and the token returns it as markup.
   */
  public function testTokenReturnsEscapedMarkup(): void {
    $submission = $this->submission(['special_instructions' => "<script>alert(1)</script>\nSecond line"]);
    $html = $this->container->get('token')->replace('[webform_submission:cut_sheet]', ['webform_submission' => $submission]);
    $this->assertStringContainsString('<p><strong>Customer:</strong> Jane Doe.', (string) $html);
    $this->assertStringContainsString("&lt;script&gt;alert(1)&lt;/script&gt;<br>\nSecond line", (string) $html);
    $this->assertStringNotContainsString('<script>', (string) $html);
    $this->assertStringNotContainsString('&lt;p&gt;', (string) $html);

    $url = $this->container->get('token')->replace('[webform_submission:cut_sheet_print_url]', ['webform_submission' => $submission]);
    $this->assertStringEndsWith('/submission/' . $submission->id() . '/cut-sheet', (string) $url);
  }

  /**
   * Other webforms get no cut sheet token.
   */
  public function testTokenIgnoresOtherWebforms(): void {
    Webform::create(['id' => 'other', 'title' => 'Other'])->save();
    $submission = WebformSubmission::create(['webform_id' => 'other', 'data' => []]);
    $submission->save();
    $result = $this->container->get('token')->replace('[webform_submission:cut_sheet]', ['webform_submission' => $submission], ['clear' => FALSE]);
    $this->assertSame('[webform_submission:cut_sheet]', (string) $result);
    $this->assertNotInstanceOf(Markup::class, $result);
  }

}
