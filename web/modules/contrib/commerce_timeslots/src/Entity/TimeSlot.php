<?php

namespace Drupal\commerce_timeslots\Entity;

use Drupal\commerce_timeslots\Interfaces\TimeSlotInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;

/**
 * Defines the Commerce time slot entity.
 *
 * @ingroup timeslot
 *
 * @ContentEntityType(
 *   id = "commerce_timeslot",
 *   label = @Translation("Time slot"),
 *   label_collection = @Translation("Time slots"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\commerce_timeslots\Form\TimeSlotForm",
 *       "edit" = "Drupal\commerce_timeslots\Form\TimeSlotForm",
 *       "delete" = "Drupal\commerce_timeslots\Form\TimeSlotDeleteForm",
 *     },
 *     "access" = "Drupal\commerce_timeslots\Access\TimeSlotAccessControlHandler",
 *     "list_builder" = "Drupal\commerce_timeslots\TimeSlotsListBuilder",
 *   },
 *   base_table = "commerce_timeslot",
 *   data_table = "commerce_timeslot_field_data",
 *   admin_permission = "administer commerce timeslot entity",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name",
 *   },
 *   links = {
 *     "collection" = "/admin/commerce/timeslots",
 *     "canonical" = "/admin/commerce/timeslots/{commerce_timeslot}",
 *     "add-form" = "/admin/commerce/timeslots/add",
 *     "edit-form" = "/admin/commerce/timeslots/{commerce_timeslot}/edit",
 *     "delete-form" = "/admin/commerce/timeslots/{commerce_timeslot}/delete",
 *   }
 * )
 */
class TimeSlot extends ContentEntityBase implements TimeSlotInterface {

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
  public static function getTimeSlotTypes(): array {
    return [
      'delivery' => t('Delivery'),
      'fast_delivery' => t('Fast delivery'),
      'pickup' => t('Pickup'),
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
      ->setDescription(new TranslatableMarkup('The ID of the TimeSlot entity.'))
      ->setReadOnly(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))
      ->setDescription(new TranslatableMarkup('The time slot name.'))
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
      ->setLabel(new TranslatableMarkup('UUID'))
      ->setDescription(new TranslatableMarkup('The UUID of the TimeSlot entity.'))
      ->setReadOnly(TRUE);

    $fields['timeslot_day_ids'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Time slot days'))
      ->setDescription(new TranslatableMarkup('The attached time slot days.'))
      ->setSetting('target_type', 'commerce_timeslot_day')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('handler', 'default')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -18,
      ])
      ->setDisplayOptions('view', [
        'weight' => -18,
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Author'))
      ->setDescription(new TranslatableMarkup('The author of the time slot.'))
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
