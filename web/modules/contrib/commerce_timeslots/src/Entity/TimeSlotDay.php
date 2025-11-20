<?php

namespace Drupal\commerce_timeslots\Entity;

use Drupal\commerce_timeslots\Interfaces\TimeSlotDayInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;

/**
 * Defines the Commerce time slot day entity.
 *
 * @ingroup timeslot
 *
 * @ContentEntityType(
 *   id = "commerce_timeslot_day",
 *   label = @Translation("Time slot day"),
 *   label_collection = @Translation("Time slot days"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\commerce_timeslots\Form\TimeSlotDayForm",
 *       "edit" = "Drupal\commerce_timeslots\Form\TimeSlotDayForm",
 *       "delete" = "Drupal\commerce_timeslots\Form\TimeSlotDayDeleteForm",
 *     },
 *     "access" = "Drupal\commerce_timeslots\Access\TimeSlotDayAccessControlHandler",
 *     "list_builder" = "Drupal\commerce_timeslots\TimeSlotDaysListBuilder",
 *   },
 *   base_table = "commerce_timeslot_day",
 *   data_table = "commerce_timeslot_day_field_data",
 *   admin_permission = "administer commerce timeslot day entity",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name",
 *   },
 *   links = {
 *     "collection" = "/admin/commerce/timeslots/days",
 *     "canonical" = "/admin/commerce/timeslots/days/{commerce_timeslot_day}",
 *     "add-form" = "/admin/commerce/timeslots/days/add",
 *     "edit-form" = "/admin/commerce/timeslots/days/{commerce_timeslot_day}/edit",
 *     "delete-form" = "/admin/commerce/timeslots/days/{commerce_timeslot_day}/delete",
 *   }
 * )
 */
class TimeSlotDay extends ContentEntityBase implements TimeSlotDayInterface {

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
  public static function getTimeSlotDayTypes(): array {
    return [
      'regular' => new TranslatableMarkup('Regular day'),
      'desired' => new TranslatableMarkup('Desired date'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getTimeSlotDays(): array {
    return [
      'sunday' => new TranslatableMarkup('Sunday'),
      'monday' => new TranslatableMarkup('Monday'),
      'tuesday' => new TranslatableMarkup('Tuesday'),
      'wednesday' => new TranslatableMarkup('Wednesday'),
      'thursday' => new TranslatableMarkup('Thursday'),
      'friday' => new TranslatableMarkup('Friday'),
      'saturday' => new TranslatableMarkup('Saturday'),
    ];
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
  public function getTimeSlotDayType(): string {
    $timeslotday_type = $this->get('timeslotday_type')->value;
    if (!empty($timeslotday_type)) {
      return self::getTimeSlotDayTypes()[$timeslotday_type];
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeSlotDay(): string {
    $timeslot_day = $this->get('timeslot_day')->value;
    if (!empty($timeslot_day)) {
      return self::getTimeSlotDays()[$timeslot_day];
    }
    return '';
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
      ->setDescription(new TranslatableMarkup('The ID of the time slot day entity.'))
      ->setReadOnly(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))
      ->setDescription(new TranslatableMarkup('The time slot day name.'))
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

    // The entity uuid unique value.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the time slot day entity.'))
      ->setReadOnly(TRUE);

    // The type of the time slot.
    $fields['timeslotday_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Day type'))
      ->setDescription(new TranslatableMarkup('The type of the time slot day entity.'))
      ->setSettings([
        'allowed_values' => self::getTimeSlotDayTypes(),
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -19,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -19,
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // The days list of the time slot day.
    $fields['timeslot_day'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Day'))
      ->setDescription(new TranslatableMarkup('The day value.'))
      ->setSettings([
        'allowed_values' => self::getTimeSlotDays(),
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -18,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -18,
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['desired_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Desired date'))
      ->setDescription(new TranslatableMarkup('The desired date when it will be shown on the calendar.'))
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', ['type' => 'date'])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setTranslatable(TRUE);

    $fields['timeslot_day_capacity_ids'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Time slot day capacities'))
      ->setDescription(new TranslatableMarkup('The attached time slot day capacities.'))
      ->setSetting('target_type', 'commerce_timeslot_day_capacity')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('handler', 'default')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -17,
      ])
      ->setDisplayOptions('view', [
        'weight' => -17,
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Author'))
      ->setDescription(new TranslatableMarkup('The author of the time slot day.'))
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
