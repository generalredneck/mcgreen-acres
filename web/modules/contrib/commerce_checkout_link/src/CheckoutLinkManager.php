<?php

namespace Drupal\commerce_checkout_link;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Utility class to generate checkout links.
 */
class CheckoutLinkManager {

  /**
   * Helper.
   */
  public static function generateUrl(OrderInterface $order, $use_changed_time = TRUE) {
    $timestamp = time();
    return new Url('commerce_checkout_link.checkout_link', [
      'commerce_order' => $order->id(),
      'timestamp' => $timestamp,
      'hash' => self::generateHash($timestamp, $order, $use_changed_time),
    ]);
  }

  /**
   * Helper.
   */
  public static function generateHash($timestamp, OrderInterface $commerce_order, $use_changed_time = TRUE) {
    $changed_time_string = '';
    if ($use_changed_time) {
      $changed_time_string = $commerce_order->getChangedTime();
    }
    return Crypt::hmacBase64($timestamp . $commerce_order->id() . $changed_time_string, Settings::getHashSalt());
  }

}
