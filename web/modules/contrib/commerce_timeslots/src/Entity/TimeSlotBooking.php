<?php

namespace Drupal\commerce_timeslots\Entity;

use Drupal\commerce_timeslots\Interfaces\TimeSlotBookingInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;

/**
 * Defines the Commerce time slot booking entity.
 *
 * @ingroup timeslot
 *
 * @ContentEntityType(
 *   id = "commerce_timeslot_booking",
 *   label = @Translation("Time slot booking"),
 *   label_collection = @Translation("Time slot bookings"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "\Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "\Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\commerce_timeslots\Form\TimeSlotBookingDeleteForm",
 *     },
 *     "access" = "Drupal\commerce_timeslots\Access\TimeSlotBookingAccessControlHandler",
 *     "list_builder" = "Drupal\commerce_timeslots\TimeSlotBookingsListBuilder",
 *   },
 *   base_table = "commerce_timeslot_booking",
 *   admin_permission = "administer commerce timeslot bookings",
 *   fieldable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/commerce/timeslots/booking",
 *     "canonical" = "/admin/commerce/timeslots/booking/{commerce_timeslot_booking}",
 *     "delete-form" = "/admin/commerce/timeslots/booking/{commerce_timeslot_booking}/delete",
 *   }
 * )
 */
class TimeSlotBooking extends ContentEntityBase implements TimeSlotBookingInterface {

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
  public static function getStatuses(): array {
    return [
      'active' => new TranslatableMarkup('Active'),
      'processed' => new TranslatableMarkup('Processed'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // The entity id.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setDescription(new TranslatableMarkup('The ID of the time slot booking entity.'))
      ->setReadOnly(TRUE);

    // The entity uuid unique value.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(new TranslatableMarkup('UUID'))
      ->setDescription(new TranslatableMarkup('The UUID of the time slot day entity.'))
      ->setReadOnly(TRUE);

    // The entity order id.
    $fields['order_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Order ID'))
      ->setSetting('target_type', 'commerce_order')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    // The entity time slot ID.
    $fields['timeslot_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Time slot ID'))
      ->setSetting('target_type', 'commerce_timeslot')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    // The entity time slot day capacity ID.
    $fields['timeslot_day_capacity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Time slot day capacity ID'))
      ->setSetting('target_type', 'commerce_timeslot_day_capacity')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['timeslot_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Date'))
      ->setSetting('datetime_type', 'datetime')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // The entity status.
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setSettings([
        'allowed_values' => self::getStatuses(),
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Author'))
      ->setDescription(new TranslatableMarkup('The author of the time slot booking.'))
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
