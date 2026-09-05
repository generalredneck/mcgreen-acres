<?php

namespace Drupal\simplenews\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simplenews\SubscriberHistoryInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Defines the simplenews subscriber history entity.
 *
 * @ContentEntityType(
 *   id = "simplenews_subscriber_history",
 *   label = @Translation("Simplenews subscriber history"),
 *   label_collection = @Translation("Subscription history"),
 *   label_singular = @Translation("subscription history record"),
 *   label_plural = @Translation("subscription history records"),
 *   handlers = {
 *     "storage_schema" = "Drupal\simplenews\Storage\SubscriberHistoryStorageSchema",
 *     "list_builder" = "Drupal\simplenews\SubscriberHistoryListBuilder",
 *     "views_data" = "Drupal\simplenews\SubscriberHistoryViewsData",
 *     "access" = "Drupal\simplenews\SubscriberHistoryAccessControlHandler",
 *   },
 *   base_table = "simplenews_subscriber_history",
 *   admin_permission = "administer simplenews subscriptions",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "mail",
 *   },
 *   links = {
 *     "collection" = "/admin/people/simplenews/history",
 *   },
 * )
 */
class SubscriberHistory extends ContentEntityBase implements SubscriberHistoryInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getMail() {
    return $this->get('mail')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp() {
    return $this->get('timestamp')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthor() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    // Note: this method previously called $this->t() (fatal - the class had no
    // StringTranslationTrait) and destructured explode() without a colon (PHP
    // warning). It never mattered while the value was unused; the history GUI
    // (#3384939) surfaces it, so harden it here.
    $source = (string) $this->get('source')->value;
    [$type, $value] = array_pad(explode(':', $source, 2), 2, '');
    switch ($type) {
      case 'route':
        if ($value === '' || $value === '<none>') {
          // Recorded outside a web request: import, Drush, cron or migration.
          return $this->t('Programmatic');
        }
        try {
          $route = \Drupal::service('router.route_provider')->getRouteByName($value);
          // Not every route defines a _title; fall back to the route name.
          return $route->getDefault('_title') ?? $value;
        }
        catch (RouteNotFoundException) {
          return $value;
        }
    }
    return $source;
  }

  /**
   * {@inheritdoc}
   */
  public function isSubscribed(string $newsletter_id) {
    foreach ($this->get('subscriptions') as $item) {
      if ($item->target_id == $newsletter_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscribedNewsletterIds() {
    $ids = [];
    foreach ($this->get('subscriptions') as $delta => $item) {
      $ids[$delta] = $item->target_id;
    }
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['mail'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setRequired(TRUE);

    $fields['timestamp'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Timestamp'))
      ->setRequired(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The user who made the change.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user');

    $fields['source'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source'))
      ->setDescription(t('How the change was made.'))
      ->setRequired(TRUE);

    $fields['subscriptions'] = BaseFieldDefinition::create('entity_reference')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setLabel(t('Subscriptions'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'simplenews_newsletter');

    return $fields;
  }

}
