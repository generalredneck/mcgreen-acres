<?php

namespace Drupal\mailchimp_signup\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Mailchimp Signup controller.
 */
class MailchimpSignupController extends ControllerBase {

  /**
   * View a Mailchimp signup form as a page.
   *
   * @param string $signup_id
   *   The ID of the MailchimpSignup entity to view.
   *
   * @return array
   *   Renderable array of page content.
   */
  public function page($signup_id) {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['mailchimp-signup-subscribe-form-page'],
      ],
      'form' => [
        '#lazy_builder' => [
          'mailchimp_signup.lazy_builder:buildForm',
          [$signup_id, 'page', 0],
        ],
        '#create_placeholder' => TRUE,
        '#cache' => [
          'keys' => [
            'mailchimp_signup_subscribe_page',
            $signup_id,
          ],
        ],
      ],
    ];
  }

}