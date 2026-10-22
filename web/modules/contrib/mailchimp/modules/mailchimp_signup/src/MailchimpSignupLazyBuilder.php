<?php

namespace Drupal\mailchimp_signup;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\mailchimp\ApiService;
use Drupal\mailchimp_signup\Form\MailchimpSignupPageForm;

/**
 * Lazy builder for Mailchimp signup forms.
 */
class MailchimpSignupLazyBuilder {

  /**
   * The Mailchimp API service.
   *
   * @var \Drupal\mailchimp\ApiService
   */
  protected ApiService $mailchimpApi;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a MailchimpSignupLazyBuilder object.
   *
   * @param \Drupal\mailchimp\ApiService $mailchimp_api
   *   The Mailchimp API service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(ApiService $mailchimp_api, FormBuilderInterface $form_builder, EntityTypeManagerInterface $entity_type_manager) {
    $this->mailchimpApi = $mailchimp_api;
    $this->formBuilder = $form_builder;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Builds the Mailchimp signup form.
   *
   * @param string $signup_id
   *   The signup entity ID.
   * @param string $context
   *   The context ('block' or 'page').
   * @param int $instance_number
   *   The instance number for multiple forms on the same page.
   *
   * @return array
   *   A render array containing the signup form.
   */
  #[TrustedCallback]
  public function buildForm(string $signup_id, string $context = 'block', int $instance_number = 0): array {
    /** @var \Drupal\mailchimp_signup\Entity\MailchimpSignup $signup */
    $signup = mailchimp_signup_load($signup_id);

    // Build the form_id based on context.
    $form_id = 'mailchimp_signup_subscribe_' . $context . '_' . $signup->id() . '_form';
    if ($instance_number > 0) {
      $form_id = sprintf('%s_%d', $form_id, $instance_number);
    }

    $form = new MailchimpSignupPageForm($this->mailchimpApi);
    $form->setFormID($form_id);
    $form->setSignup($signup);

    $content = $this->formBuilder->getForm($form);

    return $content;
  }

}
