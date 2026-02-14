<?php

namespace Drupal\mcgreen_acres_store\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_profile_pane\Plugin\Commerce\CheckoutPane\ProfileForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a custom profile form checkout pane.
 *
 * @CommerceCheckoutPane(
 *   id = "profile_form",
 *   label = @Translation("User profile form"),
 *   default_step = "_disabled",
 *   wrapper_element = "fieldset",
 *   deriver = "Drupal\commerce_profile_pane\Plugin\Derivative\ProfileFormCheckoutPaneDeriver",
 * )
 */
class CustomProfileForm extends ProfileForm {

  /**
   * {@inheritdoc}
   */

  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Get the profile entity from the form state.
    $profile = $form_state->getValue('profile_form:phone')['profile'];
    $this->order->set('field_order_contact', $profile['field_phone']);
  }

}
