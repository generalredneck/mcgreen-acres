<?php

namespace Drupal\commerce_timeslots\Interfaces;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a time slot day entity.
 *
 * @ingroup timeslot
 */
interface TimeSlotDayInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Return the list of time slot day types as options list.
   *
   * @return array
   *   The list of time slot day types keyed.
   */
  public static function getTimeSlotDayTypes(): array;

  /**
   * Return the list of time slot days list.
   *
   * @return array
   *   The list of time slot days keyed.
   */
  public static function getTimeSlotDays(): array;

  /**
   * Get the time slot day type label.
   *
   * @return string
   *   The day slot type regular/desired or empty string if not available.
   */
  public function getTimeSlotDayType(): string;

  /**
   * Get the time slot day label.
   *
   * @return string
   *   The time slot day label.
   */
  public function getTimeSlotDay(): string;

}
