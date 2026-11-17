<?php

namespace Drupal\mcgreen_cut_sheet;

use Drupal\Component\Utility\Html;
use Drupal\webform\Entity\WebformOptions;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Turns a pig cut sheet submission into plain-English paragraphs.
 *
 * The processor reads this, so it is written out in full: no abbreviations
 * and no option codes. Option wording comes from the webform's option lists
 * (the processor-specific part), so only the sentence structure lives here.
 *
 * A few option keys are a contract between the option lists and this class
 * and the webform's #states:
 * - keep / grind: tenderloin and baby back ribs (cut_sheet_keep_or_grind).
 * - A ham cut whose key ends in "steaks" is asked for a size.
 *
 * Each section is an array with a "heading" (NULL for the closing line) and
 * a "text".
 */
class CutSheetNarrative {

  /**
   * The webform this narrative is written for.
   */
  const WEBFORM_ID = 'pig_cut_sheet';

  /**
   * What a blank size field reads as.
   */
  const DEFAULT_SIZE = 'processor default size';

  /**
   * What a blank bacon slice field reads as.
   */
  const DEFAULT_SLICE = 'processor default slice';

  /**
   * The blank the processor fills in by hand.
   */
  const BLANK_HOG_ID = '______';

  /**
   * Builds the sections for a submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A submission of the pig cut sheet webform.
   *
   * @return array[]
   *   Sections, each with "heading" (string|null) and "text" (string).
   */
  public function build(WebformSubmissionInterface $webform_submission): array {
    $webform = $webform_submission->getWebform();
    $cache = [];
    $label = function (string $element_key, string $value) use ($webform, &$cache): string {
      if (!isset($cache[$element_key])) {
        $cache[$element_key] = $this->getOptions($webform, $element_key);
      }
      return $cache[$element_key][$value] ?? str_replace('_', ' ', $value);
    };
    return $this->buildFromData($webform_submission->getData(), $label);
  }

  /**
   * Builds the sections from raw submission data.
   *
   * @param array $data
   *   The submission data, keyed by element key.
   * @param callable $label
   *   Called with an element key and an option value, returns the option's
   *   label.
   *
   * @return array[]
   *   Sections, each with "heading" (string|null) and "text" (string).
   */
  public function buildFromData(array $data, callable $label): array {
    $whole = $this->value($data, 'hog_type') !== 'half';
    $sections = [];

    $sections[] = [
      'heading' => 'Customer',
      'text' => $this->customerText($data, $whole),
    ];

    $sections[] = [
      'heading' => $whole ? 'Loins' : 'Loin',
      'text' => $this->loinsText($data, $whole, $label),
    ];

    $sections[] = [
      'heading' => 'Spare ribs',
      'text' => $this->choiceText($data, 'spare_ribs', $label) . '.',
    ];

    $sections[] = [
      'heading' => $whole ? 'Shoulders' : 'Shoulder',
      'text' => $this->partsText($data, $whole, 'shoulder', 'Shoulder', 'shoulders_same', $label, 'size'),
    ];

    $sections[] = [
      'heading' => $whole ? 'Hams' : 'Ham',
      'text' => $this->partsText($data, $whole, 'ham', 'Ham', 'hams_same', $label, 'size'),
    ];

    $sections[] = [
      'heading' => 'Bacon',
      'text' => $this->partsText($data, $whole, 'bacon', 'Slab', 'bacon_same', $label, 'slice'),
    ];

    $sections[] = [
      'heading' => 'Jowls',
      'text' => $this->choiceText($data, 'jowl_cut', $label) . '.',
    ];

    $sections[] = [
      'heading' => 'Heart and liver',
      'text' => 'Heart: ' . $this->choiceText($data, 'heart', $label) . '. Liver: ' . $this->choiceText($data, 'liver', $label) . '.',
    ];

    $sections[] = [
      'heading' => 'Ground meat',
      'text' => $this->groundMeatText($data, $label),
    ];

    $sections[] = [
      'heading' => 'Special instructions',
      'text' => $this->value($data, 'special_instructions') ?: 'None.',
    ];

    $sections[] = [
      'heading' => NULL,
      'text' => 'Please contact the customer directly with any questions about these instructions.',
    ];

    return $sections;
  }

  /**
   * Renders sections as plain text.
   *
   * @param array[] $sections
   *   Sections from ::build().
   *
   * @return string
   *   One paragraph per section, separated by blank lines.
   */
  public function toText(array $sections): string {
    $paragraphs = [];
    foreach ($sections as $section) {
      $paragraphs[] = ($section['heading'] !== NULL ? $section['heading'] . ': ' : '') . $section['text'];
    }
    return implode("\n\n", $paragraphs);
  }

  /**
   * Renders sections as HTML paragraphs, safe to place in an email.
   *
   * @param array[] $sections
   *   Sections from ::build().
   *
   * @return string
   *   Markup, with all submitted text escaped.
   */
  public function toHtml(array $sections): string {
    $paragraphs = [];
    foreach ($sections as $section) {
      $heading = $section['heading'] !== NULL ? '<strong>' . Html::escape($section['heading']) . ':</strong> ' : '';
      $paragraphs[] = '<p>' . $heading . nl2br(Html::escape($section['text']), FALSE) . '</p>';
    }
    return implode("\n", $paragraphs);
  }

  /**
   * The opening paragraph: who, which hog, which day.
   */
  protected function customerText(array $data, bool $whole): string {
    $backup = $this->value($data, 'customer_backup_phone');
    $phones = 'Cell phone ' . $this->value($data, 'customer_cell_phone')
      . ($backup !== '' ? ', backup phone ' . $backup . '.' : '. No backup phone.');

    $text = $this->sentence($this->value($data, 'customer_name')) . ' ' . $phones;
    $text .= $whole ? ' Whole hog.' : ' Half hog.';
    if (!$whole) {
      $owner = $this->value($data, 'other_half_owner');
      $text .= $owner !== '' ? " Other half's owner: " . $this->sentence($owner) : " Other half's owner: not given.";
    }
    $text .= ' Hog ID: ' . static::BLANK_HOG_ID . '.';
    $text .= ' Slaughter date: ' . $this->formatDate($this->value($data, 'slaughter_date')) . '.';
    return $text;
  }

  /**
   * Loin paragraph: each loin's chops plus what happens to the extras.
   *
   * @param array $data
   *   The submission data.
   * @param bool $whole
   *   TRUE for a whole hog, FALSE for a half.
   * @param callable $label
   *   Returns an option's label.
   */
  protected function loinsText(array $data, bool $whole, callable $label): string {
    $sentences = [];
    foreach ($this->parts($data, $whole, 'loins_same') as $part) {
      $n = $part['n'];
      $cut = $this->value($data, "loin_{$n}_cut");
      $sentence = $this->lead('Loin', $part['position'], $whole, $this->optionText($label, 'loin_1_cut', $cut));
      $size = $this->value($data, "loin_{$n}_chop_size");
      $sentence .= ', ' . ($size !== '' ? rtrim($size, ". \t") : static::DEFAULT_SIZE) . '.';

      // Tenderloin and baby back ribs are only a decision on a boneless loin.
      if ($cut === 'boneless') {
        $sentence .= $this->extrasText(
          [
            'tenderloin' => $this->value($data, "loin_{$n}_tenderloin"),
            'baby back ribs' => $this->value($data, "loin_{$n}_baby_back_ribs"),
          ],
          $whole ? 'loin ' . $part['position'] : 'the loin'
        );
      }
      $sentences[] = $sentence;
    }
    return implode(' ', $sentences);
  }

  /**
   * Keep / grind sentences for the loin extras.
   *
   * @param string[] $decisions
   *   Decision per part name: "keep", "grind" or "".
   * @param string $from
   *   Where the parts come from, e.g. "loin 2".
   */
  protected function extrasText(array $decisions, string $from): string {
    $keep = array_keys(array_filter($decisions, fn ($d) => $d === 'keep'));
    $grind = array_keys(array_filter($decisions, fn ($d) => $d === 'grind'));
    $unanswered = array_keys(array_filter($decisions, fn ($d) => $d !== 'keep' && $d !== 'grind'));

    $text = '';
    if ($keep) {
      $text .= ' Keep the ' . implode(' and ', $keep) . ' from ' . $from . '.';
    }
    if ($grind) {
      $text .= ' Grind the ' . implode(' and ', $grind) . ' from ' . $from . ' into ground meat.';
    }
    if ($unanswered) {
      $text .= ' No choice was made for the ' . implode(' and ', $unanswered) . ' from ' . $from . '.';
    }
    return $text;
  }

  /**
   * Shoulder, ham and bacon paragraphs.
   *
   * @param array $data
   *   The submission data.
   * @param bool $whole
   *   TRUE for a whole hog, FALSE for a half.
   * @param string $group
   *   Element prefix: shoulder, ham or bacon.
   * @param string $noun
   *   What a part is called in the sentence: Shoulder, Ham or Slab.
   * @param string $same_key
   *   The "same for both" checkbox element key.
   * @param callable $label
   *   Returns an option's label.
   * @param string $size_kind
   *   Either "size" or "slice"; picks the blank-size wording.
   */
  protected function partsText(array $data, bool $whole, string $group, string $noun, string $same_key, callable $label, string $size_kind): string {
    $sentences = [];
    foreach ($this->parts($data, $whole, $same_key) as $part) {
      $n = $part['n'];
      $cut = $this->value($data, "{$group}_{$n}_cut");
      $sentence = $this->lead($noun, $part['position'], $whole, $this->optionText($label, "{$group}_1_cut", $cut));

      // Hams only ask for a size when steaks are chosen. Bacon always asks for
      // a slice, shoulders always ask for a size.
      $asks = $group !== 'ham' || str_ends_with($cut, 'steaks');
      if ($asks) {
        $size = $this->value($data, $group === 'bacon' ? "bacon_{$n}_slice_size" : "{$group}_{$n}_size");
        $default = $size_kind === 'slice' ? static::DEFAULT_SLICE : static::DEFAULT_SIZE;
        $sentence .= ', ' . ($size !== '' ? rtrim($size, ". \t") : $default);
      }
      $sentences[] = $sentence . '.';
    }
    return implode(' ', $sentences);
  }

  /**
   * Ground meat paragraph: ground pork, or the sausage types and pounds.
   */
  protected function groundMeatText(array $data, callable $label): string {
    if ($this->value($data, 'ground_use') !== 'sausage') {
      return 'All ground meat to ' . $this->lcfirst($this->optionText($label, 'ground_use', $this->value($data, 'ground_use') ?: 'ground_pork')) . '.';
    }

    $types = $data['sausage_types'] ?? [];
    $types = is_array($types) ? array_values(array_filter($types)) : [];
    $sentences = [];
    foreach ($types as $type) {
      $pounds = $this->value($data, 'sausage_lb_' . $type);
      $sentence = ucfirst(rtrim($this->optionText($label, 'sausage_types', $type), '.'));
      $sentences[] = $sentence . ($pounds !== '' ? ', about ' . $this->formatPounds($pounds) . ' lb.' : '.');
    }
    return 'Make sausage from the ground meat. ' . implode(' ', $sentences);
  }

  /**
   * Which parts the customer answered for.
   *
   * A half hog has one of each part. A whole hog has two, and when "same for
   * both" is checked the second part repeats the first's answers.
   *
   * @param array $data
   *   The submission data.
   * @param bool $whole
   *   TRUE for a whole hog, FALSE for a half.
   * @param string $same_key
   *   The "same for both" checkbox element key.
   *
   * @return array[]
   *   Each has "n": the element number whose answers describe the part, and
   *   "position": the part's own number, 1 or 2.
   */
  protected function parts(array $data, bool $whole, string $same_key): array {
    $parts = [['n' => 1, 'position' => 1]];
    if ($whole) {
      $parts[] = ['n' => !empty($data[$same_key]) ? 1 : 2, 'position' => 2];
    }
    return $parts;
  }

  /**
   * Starts a part's sentence with what was chosen for it.
   *
   * A whole hog names each part ("Loin 1, bone-in chops"). On a half hog the
   * section heading already names the single part, so the sentence starts
   * with the choice ("Bone-in chops").
   */
  protected function lead(string $noun, int $position, bool $whole, string $choice): string {
    return $whole
      ? $noun . ' ' . $position . ', ' . $this->lcfirst($choice)
      : mb_strtoupper(mb_substr($choice, 0, 1)) . mb_substr($choice, 1);
  }

  /**
   * An option's label, or its value when the option no longer exists.
   */
  protected function optionText(callable $label, string $element_key, string $value): string {
    return $value === '' ? 'not chosen' : $label($element_key, $value);
  }

  /**
   * The label for a single-choice element's current value.
   */
  protected function choiceText(array $data, string $key, callable $label): string {
    return rtrim($this->optionText($label, $key, $this->value($data, $key)), '.');
  }

  /**
   * Gets a trimmed string value from submission data.
   */
  protected function value(array $data, string $key): string {
    $value = $data[$key] ?? '';
    return is_scalar($value) ? trim((string) $value) : '';
  }

  /**
   * Ends a piece of free text with a period unless it already has one.
   */
  protected function sentence(string $text): string {
    return preg_match('/[.!?]$/', $text) ? $text : $text . '.';
  }

  /**
   * Lower-cases the first character, so a label reads mid-sentence.
   */
  protected function lcfirst(string $text): string {
    return mb_strtolower(mb_substr($text, 0, 1)) . mb_substr($text, 1);
  }

  /**
   * Formats a Y-m-d date as "December 2", or passes other text through.
   */
  protected function formatDate(string $value): string {
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date ? $date->format('F j') : ($value !== '' ? $value : static::BLANK_HOG_ID);
  }

  /**
   * Formats a pound amount without a trailing ".0".
   */
  protected function formatPounds(string $value): string {
    if (!is_numeric($value)) {
      return $value;
    }
    return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
  }

  /**
   * Loads an element's option list, keyed by value.
   */
  protected function getOptions(WebformInterface $webform, string $element_key): array {
    $element = $webform->getElement($element_key);
    if (!$element) {
      return [];
    }
    $options = WebformOptions::getElementOptions($element);
    $flat = [];
    foreach ($options as $key => $value) {
      if (is_array($value)) {
        foreach ($value as $sub_key => $sub_value) {
          $flat[$sub_key] = (string) $sub_value;
        }
      }
      else {
        $flat[$key] = (string) $value;
      }
    }
    return $flat;
  }

}
