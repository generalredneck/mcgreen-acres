<?php

namespace Drupal\commerce_timeslots\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting a commerce time slot day capacity entity.
 *
 * @ingroup timeslot
 */
class TimeSlotDayCapacityDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    // Set redirect to the time slots day capacity listing page.
    $form_state->setRedirect('entity.commerce_timeslot_day_capacity.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.commerce_timeslot_day_capacity.collection');
  }

}
