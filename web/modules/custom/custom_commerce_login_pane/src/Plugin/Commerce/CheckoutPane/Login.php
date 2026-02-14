<?php

namespace Drupal\custom_commerce_login_pane\Plugin\Commerce\CheckoutPane;

use Drupal\Component\Utility\Random;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\Login as CheckoutPaneLogin;

/**
 * Provides the login pane.
 */
#[CommerceCheckoutPane(
  id: "login",
  label: new TranslatableMarkup('Log in or continue as guest'),
  admin_description: new TranslatableMarkup("Presents customers with the choice to log in or proceed as a guest during checkout."),
  default_step: "login",
  wrapper_element: "container",
)]

class Login extends CheckoutPaneLogin {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $form = parent::buildPaneForm($pane_form, $form_state, $complete_form);
    $form['guest']['#weight'] = -10;
    $form['register']['#weight'] = -5;
    $form['returning_customer']['#weight'] = 0;

    $form['returning_customer']['name']['#title'] = 'Email address';
    $form['returning_customer']['forgot_password']['#attributes']['class'] = ['btn', 'btn-outline-primary'];
    $form['register']['name']['#type'] = 'hidden';
    $form['register']['name']['#value'] = \Drupal::service('uuid')->generate();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    parent::validatePaneForm($pane_form, $form_state, $complete_form);

    $triggering_element = $form_state->getTriggeringElement();
    $trigger = !empty($triggering_element['#op']) ? $triggering_element['#op'] : 'continue';
    if (!$form_state->hasAnyErrors() && $trigger === 'register') {
      if ($uid = $form_state->get('logged_in_uid')) {
        /** @var \Drupal\user\UserInterface $account */
        $account = $this->entityTypeManager->getStorage('user')->load($uid);
        _user_mail_notify('register_no_approval_required', $account);
      }
    }
  }

}
