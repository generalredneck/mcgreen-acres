<?php

namespace Drupal\commerce_timeslots\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implementation of TimeSlotDayForm edit and create forms.
 */
class TimeSlotDayForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['warning'] = [
      '#markup' => $this->t('The time slot day form.'),
      '#weight' => -100,
    ];

    // Add default none value for cases when there are no any time slot day
    // capacities defined in the system.
    if (empty($form['timeslot_day_capacity_ids']['widget']['#options'])) {
      $form['timeslot_day_capacity_ids']['widget']['#options']['_none'] = $this->t('- None -');
    }

    // Display author information.
    $form['author'] = [
      '#markup' => $this->t('<strong>Author:</strong> <em>@author</em>', [
        '@author' => $this->entity->getOwner()->label(),
      ]),
    ];

    // Set the min/max desired date value requirement.
    $min = new DrupalDateTime('+1 day', 'UTC');
    $max = new DrupalDateTime('+31 day', 'UTC');
    $desired_date = &$form['desired_date']['widget'][0]['value'];
    $desired_date['#attributes']['min'] = $min->format($desired_date['#date_date_format']);
    $desired_date['#attributes']['max'] = $max->format($desired_date['#date_date_format']);
    // Add a wrapper for the desired date fieldset.
    $form['desired_date']['#prefix'] = '<div id="desired-date-wrp" style="display:none;">';
    $form['desired_date']['#suffix'] = '</div>';

    // Attach the admin js library for time slow day form.
    $form['#attached']['library'][] = 'commerce_timeslots/timeslots_admin';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->messenger()->addMessage($this->t('The time slot day %label have been saved.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.commerce_timeslot_day.collection');
  }

}
