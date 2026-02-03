<?php

namespace Drupal\commerce_timeslots\Interfaces;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a time slot booking entity.
 *
 * @ingroup timeslot
 */
interface TimeSlotBookingInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Return the list of the available time slot booking statuses.
   *
   * @return array
   *   The list of time slot booking statuses keyed.
   */
  public static function getStatuses(): array;

}
