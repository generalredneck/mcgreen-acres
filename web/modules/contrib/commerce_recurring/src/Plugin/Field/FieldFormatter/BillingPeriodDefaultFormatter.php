<?php

namespace Drupal\commerce_recurring\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Provides the default billing period formatter.
 *
 * @FieldFormatter(
 *   id = "commerce_billing_period_default",
 *   module = "commerce_recurring",
 *   label = @Translation("Billing period"),
 *   field_types = {
 *     "commerce_billing_period"
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class BillingPeriodDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $build = [];
    /** @var \Drupal\commerce_recurring\Plugin\Field\FieldType\BillingPeriodItem $item */
    foreach ($items as $delta => $item) {
      $billing_period = $item->toBillingPeriod();
      $start_date = $billing_period->getStartDate();
      $end_date = $billing_period->getEndDate();

      // Check if this is a prepaid billing period and adjust dates accordingly.
      $entity = $items->getEntity();
      if ($entity instanceof OrderInterface &&
        $entity->hasField('billing_schedule') &&
        !$entity->get('billing_schedule')->isEmpty()) {
        $billing_schedule = $entity->get('billing_schedule')->entity;
        if ($billing_schedule &&
          $billing_schedule->getBillingType() == 'prepaid') {
          // Clone dates and add the interval
          $interval = $billing_schedule->getPlugin()->getConfiguration()['interval'];
          $interval_string = '+' . $interval['number'] . ' ' . $interval['unit'];

          $start_date = clone $start_date;
          $end_date = clone $end_date;
          $start_date->modify($interval_string);
          $end_date->modify($interval_string);
        }
      }

      $start_date_formatted = $start_date->format('M jS Y H:i:s');
      $end_date_formatted = $end_date->format('M jS Y H:i:s');

      $build[$delta] = [
        '#plain_text' => $start_date_formatted . ' - ' . $end_date_formatted,
      ];
    }
    return $build;
  }

}
