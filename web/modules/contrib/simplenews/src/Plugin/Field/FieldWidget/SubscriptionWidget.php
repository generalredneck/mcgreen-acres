<?php

namespace Drupal\simplenews\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\simplenews\SubscriptionWidgetInterface;

/**
 * Plugin implementation of the 'simplenews_subscription_select' widget.
 *
 * @FieldWidget(
 *   id = "simplenews_subscription_select",
 *   label = @Translation("Select list"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class SubscriptionWidget extends OptionsButtonsWidget implements SubscriptionWidgetInterface {

  /**
   * IDs of the newsletters available for selection.
   *
   * @var string[]
   */
  protected $newsletterIds;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public function setAvailableNewsletterIds(?array $newsletter_ids = NULL) {
    // Users with the "manage simplenews hidden subscriptions" permission may
    // manage subscriptions to newsletters with a "hidden" access setting too
    // (e.g. from the account subscriptions form or the subscriber add/edit
    // forms), not just publicly visible ones.
    // See https://www.drupal.org/project/simplenews/issues/2111981.
    $newsletters = $this->currentUser->hasPermission('manage simplenews hidden subscriptions')
      ? simplenews_newsletter_get_all()
      : simplenews_newsletter_get_visible();
    $this->newsletterIds = array_keys($newsletters);
    if (isset($newsletter_ids)) {
      $this->newsletterIds = array_intersect($newsletter_ids, $this->newsletterIds);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableNewsletterIds() {
    if (!isset($this->newsletterIds)) {
      $this->setAvailableNewsletterIds();
    }
    return $this->newsletterIds;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions(FieldableEntityInterface $entity) {
    $options = array_intersect_key(parent::getOptions($entity), array_flip($this->getAvailableNewsletterIds()));

    // Flag hidden newsletters in the label. They are only ever included in
    // $options for users with the "manage simplenews hidden subscriptions"
    // permission, so make it obvious these are not normally subscriber
    // facing.
    foreach (Newsletter::loadMultiple(array_keys($options)) as $id => $newsletter) {
      if (!$newsletter->isAccessible()) {
        $options[$id] = $this->t('@label (hidden)', ['@label' => $options[$id]]);
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Preserve hidden options.
    $original = $form_state->getformObject()->getEntity()->getSubscribedNewsletterIds();
    $hidden = array_diff($original, $this->getAvailableNewsletterIds());
    return array_merge($values, $hidden);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return (($field_definition->getTargetEntityTypeId() == 'simplenews_subscriber') && $field_definition->getName() == 'subscriptions');
  }

}
