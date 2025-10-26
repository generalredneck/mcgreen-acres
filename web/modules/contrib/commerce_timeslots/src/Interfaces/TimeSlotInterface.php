<?php

namespace Drupal\commerce_timeslots\Interfaces;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a time slot entity.
 *
 * @ingroup timeslot
 */
interface TimeSlotInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Return the list of time slot types as options list.
   *
   * @return array
   *   The list of time slot types keyed.
   */
  public static function getTimeSlotTypes(): array;

}
