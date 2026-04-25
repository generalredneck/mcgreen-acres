<?php

namespace Drupal\commerce_stripe\Event;

use Drupal\commerce\EventBase;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Defines the Express checkout shipping profile alter event.
 *
 * This enables other modules to alter the shipping profile.
 *
 * @see \Drupal\commerce_stripe\Event\StripeEvents
 */
class ExpressCheckoutShippingProfileAlterEvent extends EventBase {

  /**
   * The shipping profile.
   *
   * @var \Drupal\profile\Entity\ProfileInterface
   */
  protected $shippingProfile;

  /**
   * The charge attributes.
   *
   * @var array
   */
  protected array $chargeAttributes;

  /**
   * Constructs a new ExpressCheckoutShippingProfileAlterEvent object.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $shipping_profile
   *   The shipping profile.
   * @param array $charge_attributes
   *   The charge attributes.
   */
  public function __construct(ProfileInterface $shipping_profile, array $charge_attributes = []) {
    $this->shippingProfile = $shipping_profile;
    $this->chargeAttributes = $charge_attributes;
  }

  /**
   * Gets the shipping profile.
   *
   * @return \Drupal\profile\Entity\ProfileInterface
   *   The shipping profile.
   */
  public function getShippingProfile(): ProfileInterface {
    return $this->shippingProfile;
  }

  /**
   * Gets the charge attributes array.
   *
   * @return array
   *   The charge attributes.
   */
  public function getChargeAttributes(): array {
    return $this->chargeAttributes;
  }

}
