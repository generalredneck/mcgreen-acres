<?php

namespace Drupal\commerce_timeslots\Interfaces;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a time slot day capacity entity.
 *
 * @ingroup timeslot
 */
interface TimeSlotDayCapacityInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {}
