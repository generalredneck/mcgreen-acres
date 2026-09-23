<?php

namespace Drupal\commerce_fee\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_fee\Entity\FeeInterface;

/**
 * Defines the fee event.
 *
 * @see \Drupal\commerce_fee\Event\FeeEvents
 */
class FeeEvent extends EventBase {

  /**
   * Constructs a new FeeEvent.
   *
   * @param \Drupal\commerce_fee\Entity\FeeInterface $fee
   *   The fee.
   */
  public function __construct(protected FeeInterface $fee) {}

  /**
   * Gets the fee.
   *
   * @return \Drupal\commerce_fee\Entity\FeeInterface
   *   The fee.
   */
  public function getFee(): FeeInterface {
    return $this->fee;
  }

}
