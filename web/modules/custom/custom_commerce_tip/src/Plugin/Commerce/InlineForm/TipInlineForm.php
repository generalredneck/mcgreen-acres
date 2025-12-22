<?php

namespace Drupal\custom_commerce_tip\Plugin\Commerce\InlineForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_price\Price;
use Drupal\commerce_tip\Plugin\Commerce\InlineForm\TipInlineForm as TipInlineFormBase;

/**
 * Provides an inline form for Tip.
 *
 * @CommerceInlineForm(
 *   id = "commerce_tip_inline_form",
 *   label = @Translation("Tip InlineForm"),
 * )
 */
class TipInlineForm extends TipInlineFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildInlineForm(array $inline_form, FormStateInterface $form_state) {
    $inline_form = parent::buildInlineForm($inline_form, $form_state);
    $inline_configuration = $form_state->get('inline_configuration');
    $order = $this->entityTypeManager->getStorage('commerce_order')->load($inline_configuration['order_id']);
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order_amount = $order->getTotalPrice();
    $order_amount_number = (float) $order_amount->getNumber();
    // Fees are 2.9% + $0.30 with a minimum of $0.50.
    $original_credit_card_fees_amount = ($order_amount_number * 0.029) + 0.30;
    // Calculate a 2nd round because this will be closer to what the payment
    // processor would take after someone gave a tip because they take from the
    // tip as well. The difference would be somewhere around 3% of the tip.
    $new_credit_card_fees_amount = (($order_amount_number + $original_credit_card_fees_amount) * 0.029) + 0.30;
    if ($new_credit_card_fees_amount < 0.50) {
      $new_credit_card_fees_amount = 0.50;
    }
    $tip_to_cover_fees = $new_credit_card_fees_amount / $order_amount_number;
    $options = $inline_form['tip_info']['tip_options']['#value'];
    $tip_value_formatter = $this->currencyFormatter->format(
      round($new_credit_card_fees_amount, 2),
      $order_amount->getCurrencyCode()
    );
    $new_options = [(string) round($tip_to_cover_fees, 4) => "Cover farmer's processing fees (" . $tip_value_formatter . ")" ];
    foreach ([0.05, 0.10, 0.15] as $percentage) {
      $new_options[(string) $percentage] = $this->t(
        '@percentage% (@tip_total)',
        [
          '@percentage' => round($percentage * 100),
          '@tip_total' => $this->currencyFormatter->format(
            round($percentage * $order_amount_number, 2),
            $order_amount->getCurrencyCode()
          ),
        ]
      );
    }
    $this->arraySpliceAssoc(
      $options,
      1,
      0,
      $new_options
    );
    $inline_form['tip_info']['tip']['#options'] = $options;
    $inline_form['tip_info']['tip_options']['#value'] = $options;
    $inline_form['tip_info']['tip']['#default_value'] = (string) round($tip_to_cover_fees, 4);
    unset($inline_form['tip_info']['add_tip']);
    return $inline_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitInlineForm(array &$inline_form, FormStateInterface $form_state) {
    $values = $form_state->getValue($inline_form['#parents']);
    $inline_configuration = $form_state->get('inline_configuration');
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($inline_configuration['order_id']);
    if ($order->getAdjustments(['tip'])) {
      // We don't want to duplicate the tip adjustment when someone returns to
      // the payment information page after they've already added a tip.
      // They will have to use the "Remove Tip" button to remove the tip.
      return;
    }
    $total_price = $order->getTotalPrice();
    $tip = $values['tip_info']['tip'];
    if ($values['tip_info']['tip_options'] && $values['tip_info']['tip'] == 'other') {
      $tip = $values['tip_info']['other_tip'];
    }
    elseif ($values['tip_info']['tip_options'] && $values['tip_info']['tip'] !== 'none') {
      $tip = round((float) $total_price->getNumber() * (float) $values['tip_info']['tip'], 2);
    }
    if ($values['tip_info']['tip'] !== 'none' && !empty($tip)) {
      $order->addAdjustment(new Adjustment([
        'type' => 'tip',
        'label' => 'Tip',
        'amount' => new Price($tip, $total_price->getCurrencyCode()),
        'locked' => TRUE,
        'source_id' => 'commerce_tip',
        'percentage' => NULL,
        'included' => FALSE,
      ]))->save();
    }
  }

  /**
   * Inserts a new element into an associative array at a specific position.
   *
   * @param array $input
   *   The input array.
   * @param int $offset
   *   If offset is positive then the start of the removed portion is at that
   *     offset from the beginning of the array array.
   *   If offset is negative then the start of the removed portion is at that
   *     offset from the end of the array array.
   * @param int|null $length
   *   If length is omitted, removes everything from offset to the end of the
   *     array.
   *   If length is specified and is positive, then that many elements will be
   *     removed.
   *   If length is specified and is negative, then the end of the removed
   *     portion will be that many elements from the end of the array.
   *   If length is specified and is zero, no elements will be removed.
   * @param array $replacement
   *   If replacement array is specified, then the removed elements are replaced
   *     with elements from this array.
   *   If offset and length are such that nothing is removed, then the elements
   *     from the replacement array are inserted in the place specified by the
   *     offset.
   *   If replacement is just one element it is not necessary to put array() or
   *     square brackets around it, unless the element is an array itself, an
   *     object or null.
   *   NOTE: Keys in the replacement array will be preserved.
   */
  private function arraySpliceAssoc(array &$input, int $offset, ?int $length = NULL, $replacement = []): void {
    // Cast replacement to an array to handle single-value replacements
    // correctly.
    $replacement = (array) $replacement;

    // Use array_keys() and array_flip() to find the numeric index corresponding
    // to a string key.
    $key_indices = array_flip(array_keys($input));

    // Convert string offset to numeric index if necessary.
    if (isset($input[$offset]) && is_string($offset)) {
      $offset = $key_indices[$offset];
    }

    // If length is omitted, remove all elements from the offset to the end.
    if (is_null($length)) {
      $length = count($input) - $offset;
    }

    // Convert string length (end key) to a numeric length if necessary.
    if (isset($input[$length]) && is_string($length)) {
      $length = $key_indices[$length] - $offset;
    }

    // Slice the array into three parts, preserving keys for each part
    // (the TRUE parameter).
    $part_before = array_slice($input, 0, $offset, TRUE);
    $part_after = array_slice($input, $offset + $length, NULL, TRUE);

    // Merge the parts using the array union (+) operator to preserve all keys
    // and their order.
    $input = $part_before + $replacement + $part_after;
  }

}
