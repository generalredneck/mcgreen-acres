<?php

namespace Drupal\commerce_timeslots\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The TimeSlotDaySettingsForm class.
 *
 * @ingroup timeslot
 */
class TimeSlotDaySettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_timeslots_day_settings';
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
    $form['commerce_timeslots_day_settings']['#markup'] = 'Settings form for the time slot day entity.';
    return $form;
  }

}
