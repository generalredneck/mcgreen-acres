<?php

namespace Drupal\commerce_timeslots\Entity;

use Drupal\commerce_timeslots\Interfaces\TimeSlotDayCapacityInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;

/**
 * Defines the Commerce timeSlot day capacity entity.
 *
 * @ingroup timeslot
 *
 * @ContentEntityType(
 *   id = "commerce_timeslot_day_capacity",
 *   label = @Translation("Time slot day capacity"),
 *   label_collection = @Translation("Time slot day capacities"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\commerce_timeslots\Form\TimeSlotDayCapacityForm",
 *       "edit" = "Drupal\commerce_timeslots\Form\TimeSlotDayCapacityForm",
 *       "delete" = "Drupal\commerce_timeslots\Form\TimeSlotDayCapacityDeleteForm",
 *     },
 *     "access" = "Drupal\commerce_timeslots\Access\TimeSlotDayCapacityAccessControlHandler",
 *     "list_builder" = "Drupal\commerce_timeslots\TimeSlotDayCapacitiesListBuilder",
 *   },
 *   base_table = "commerce_timeslot_day_capacity",
 *   data_table = "commerce_timeslot_day_capacity_field_data",
 *   admin_permission = "administer commerce timeslot day capacity entity",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name",
 *   },
 *   links = {
 *     "collection" = "/admin/commerce/timeslots/day-capacities",
 *     "canonical" = "/admin/commerce/timeslots/day-capacities/{commerce_timeslot_day_capacity}",
 *     "add-form" = "/admin/commerce/timeslots/day-capacities/add",
 *     "edit-form" = "/admin/commerce/timeslots/day-capacities/{commerce_timeslot_day_capacity}/edit",
 *     "delete-form" = "/admin/commerce/timeslots/day-capacities/{commerce_timeslot_day_capacity}/delete",
 *   }
 * )
 */
class TimeSlotDayCapacity extends ContentEntityBase implements TimeSlotDayCapacityInterface {

  // Implements methods defined by EntityChangedInterface.
  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   *
   * When a new entity instance is added, set the user_id entity reference to
   * the current user as the creator of the instance.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('name', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    // The entity id.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setDescription(new TranslatableMarkup('The ID of the time slot day capacity entity.'))
      ->setReadOnly(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))
      ->setDescription(new TranslatableMarkup('The time slot day capacity name.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -20,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['interval'] = BaseFieldDefinition::create('daterange')
      ->setLabel(new TranslatableMarkup('Interval'))
      ->setDescription(new TranslatableMarkup('The time slot day capacity interval.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -19,
      ])
      ->setDisplayOptions('form', [
        'type' => 'daterange_default',
        'weight' => -19,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['capacity'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Capacity'))
      ->setDescription(new TranslatableMarkup('The time slot item capacity.'))
      ->setDefaultValue(1)
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'integer',
        'weight' => -18,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // The entity uuid unique value.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(new TranslatableMarkup('UUID'))
      ->setDescription(new TranslatableMarkup('The UUID of the time slot day capacity entity.'))
      ->setReadOnly(TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Author'))
      ->setDescription(new TranslatableMarkup('The author of the time slot day capacity.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('view', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(new TranslatableMarkup('Language code'))
      ->setDescription(new TranslatableMarkup('The language code of the entity.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDescription(new TranslatableMarkup('The time when the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDescription(new TranslatableMarkup('The time when the entity was last edited.'));

    return $fields;
  }

}
