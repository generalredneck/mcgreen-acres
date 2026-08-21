<?php

namespace Drupal\commerce_checkout_link\Event;

/**
 * Events dispatched by this module.
 *
 * @package Drupal\commerce_checkout_link\Event
 */
final class CommerceCheckoutLinkEvents {

  /**
   * Name of the event fired when redirecting to checkout via link.
   *
   * @Event
   *
   * @see \Drupal\commerce_checkout_link\Event\CheckoutLinkEvent
   */
  const CHECKOUT_LINK_REDIRECT = 'commerce_checkout_link.redirect';

}
