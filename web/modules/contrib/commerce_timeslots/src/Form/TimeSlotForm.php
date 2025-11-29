<?php

namespace Drupal\commerce_timeslots\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implementation of TimeSlotForm edit and create forms.
 */
class TimeSlotForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['markup'] = [
      '#markup' => $this->t('Configure the time slot form.'),
      '#weight' => -100,
    ];

    // Add default none value for cases when there are no any time slot days
    // defined in the system.
    if (empty($form['timeslot_day_ids']['widget']['#options'])) {
      $form['timeslot_day_ids']['widget']['#options']['_none'] = $this->t('- None -');
    }

    // Display author information.
    $form['author'] = [
      '#markup' => $this->t('<strong>Author:</strong> <em>@author</em>', [
        '@author' => $this->entity->getOwner()->label(),
      ]),
    ];

    // Attach the admin js library for time slow day form.
    $form['#attached']['library'][] = 'commerce_timeslots/timeslots_admin';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->messenger()->addMessage($this->t('The time slot %label have been saved.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.commerce_timeslot.collection');
  }

}
