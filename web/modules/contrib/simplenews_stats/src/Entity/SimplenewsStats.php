<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\simplenews_stats\SimplenewsStatsInterface;
use Drupal\user\UserInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the simplenews stats entity class.
 *
 * @formatter:off
 * @ContentEntityType(
 *   id = "simplenews_stats",
 *   label = @Translation("Simplenews Stats"),
 *   label_collection = @Translation("Simplenews Stats"),
 *   handlers = {
 *     "storage" = "Drupal\simplenews_stats\SimplenewsStatsEntityStorage",
 *     "view_builder" = "Drupal\simplenews_stats\SimplenewsStatsViewBuilder",
 *     "list_builder" = "Drupal\simplenews_stats\SimplenewsStatsListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\simplenews_stats\SimplenewsStatsAccessControlHandler",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *   },
 *   base_table = "simplenews_stats",
 *   admin_permission = "administer simplenews stats",
 *   entity_keys = {
 *     "id" = "ssid",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/content/simplenews-stats/{simplenews_stats}",
 *     "delete-form" = "/admin/content/simplenews-stats/{simplenews_stats}/delete",
 *     "collection" = "/admin/content/simplenews-stats"
 *   },
 * )
 * @formatter:on
 */
class SimplenewsStats extends ContentEntityBase implements SimplenewsStatsInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   *
   * When a new simplenews stats entity is created, set the uid entity reference
   * to the current user as the creator of the entity.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += ['uid' => \Drupal::currentUser()->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): ?string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $title): SimplenewsStatsInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(bool $status): SimplenewsStatsInterface {
    $this->set('promote', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): SimplenewsStatsInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getViews(): int {
    return (int) $this->get('views')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getClicks(): int {
    return (int) $this->get('clicks')->value;
  }

  /**
   * Return the number of emails sent.
   */
  public function getTotalMails(): int {
    return (int) $this->get('total_emails')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getNewsletterEntity(): EntityInterface {
    return $this->entityTypeManager()
      ->getStorage($this->get('entity_type')->value)
      ->load($this->get('entity_id')->value);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $newsletter = $this->entityTypeManager()
      ->getStorage($this->get('entity_type')->value)
      ->load($this->get('entity_id')->value);

    return ($newsletter) ? $newsletter->label() : $this->t('Deleted');
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['snid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Simplenews subscriber ID'))
      ->setDescription(t('Simplenews subscriber Id'))
      ->setDisplayOptions('form', [
        'type' => 'integer',
        'settings' => [],
        'weight' => 16,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'hidden',
        'weight' => 16,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity Type'))
      ->setSettings(['max_length' => 64])
      ->setDescription(t('Entity Type'))
      ->setDisplayOptions('form', [
        'type' => 'string',
        'settings' => [],
        'weight' => 18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'hidden',
        'weight' => 18,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('Entity ID'))
      ->setDisplayOptions('form', [
        'type' => 'integer',
        'settings' => [],
        'weight' => 19,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'hidden',
        'weight' => 19,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['clicks'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Clicks'))
      ->setDescription(t('Clicks'))
      ->setDefaultValue(0)
      ->setDisplayOptions('form', [
        'type' => 'integer',
        'settings' => [],
        'weight' => 19,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'hidden',
        'weight' => 19,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['views'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Views'))
      ->setDescription(t('Views'))
      ->setDefaultValue(0)
      ->setDisplayOptions('form', [
        'type' => 'integer',
        'settings' => [],
        'weight' => 19,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'hidden',
        'weight' => 19,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['total_emails'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Total of Emails'))
      ->setDescription(t('Total of Emails sent'))
      ->setDefaultValue(0)
      ->setDisplayOptions('form', [
        'type' => 'integer',
        'settings' => [],
        'weight' => 19,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'hidden',
        'weight' => 19,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the simplenews stats was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 21,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 21,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', ['type' => 'hidden']);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function increaseView(): SimplenewsStatsInterface {
    $this->increaseField('views');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function increaseClick(): SimplenewsStatsInterface {
    $this->increaseField('clicks');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function increaseTotalMail(): SimplenewsStatsInterface {
    $this->increaseField('total_emails');
    return $this;
  }

  /**
   * Add one to the given field.
   *
   * @param string $field
   *   The field name to increase.
   */
  protected function increaseField(string $field): SimplenewsStatsInterface {
    if (!empty($this->{$field}) && !$this->{$field}->isEmpty()) {
      $this->{$field} = $this->get($field)->value + 1;
    }
    else {
      $this->{$field} = 1;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssociatedEntityType(): ?string {
    if (!$this->get('entity_type')->isEmpty()) {
      return $this->get('entity_type')->first()->value;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssociatedEntityId(): ?int {
    if (!$this->get('entity_id')->isEmpty()) {
      return (int) $this->get('entity_id')->first()->value;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssociatedEntity(): ?EntityInterface {
    $entity_type = $this->getAssociatedEntityType();
    $entity_id = $this->getAssociatedEntityId();
    if ($entity_type && $entity_id) {
      return $this->entityTypeManager()
        ->getStorage($entity_type)
        ->load($entity_id);
    }
    return NULL;
  }

}
