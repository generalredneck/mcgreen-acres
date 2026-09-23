<?php

namespace Drupal\mailchimp_signup\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Subscribe' block.
 *
 * @Block(
 *   id = "mailchimp_signup_subscribe_block",
 *   admin_label = @Translation("Subscribe Block"),
 *   category = @Translation("Mailchimp Signup"),
 *   module = "mailchimp_signup",
 *   deriver = "Drupal\mailchimp_signup\Plugin\Derivative\MailchimpSignupSubscribeBlock"
 * )
 */
class MailchimpSignupSubscribeBlock extends BlockBase {

  /**
   * A static counter used to generate the form_id.
   *
   * @var int
   */
  private static $counter = 0;

  /**
   * {@inheritdoc}
   */
  public function build() {
    $signup_id = $this->getDerivativeId();
    $instance_number = self::$counter++;

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['mailchimp-signup-subscribe-form-block'],
      ],
      'form' => [
        '#lazy_builder' => [
          'mailchimp_signup.lazy_builder:buildForm',
          [$signup_id, 'block', $instance_number],
        ],
        '#create_placeholder' => TRUE,
        '#cache' => [
          'keys' => [
            'mailchimp_signup_subscribe_block',
            $signup_id,
            $instance_number,
          ],
        ],
      ],
    ];
  }

}
