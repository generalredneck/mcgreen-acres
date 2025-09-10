<?php

namespace Drupal\commerce_timeslots\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The TimeSlotDayCapacitySettingsForm class.
 *
 * @ingroup timeslot
 */
class TimeSlotDayCapacitySettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_timeslots_day_capacity_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Empty implementation of the abstract submit class.
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['commerce_timeslots_day_capacity_settings']['#markup'] = 'Settings form for the time slot day capacity entity.';
    return $form;
  }

}
