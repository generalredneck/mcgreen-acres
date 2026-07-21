<?php

namespace Drupal\commerce_tip;

/**
 * Provides utility functions for commerce tip.
 */
class CommerceTipUtilities implements CommerceTipUtilitiesInterface {

  /**
   * {@inheritdoc}
   */
  public function convertTipOptions($input) {
    $result = [];
    if (!empty($input)) {
      $lines = explode(",", trim($input));

      foreach ($lines as $line) {
        [$value, $percentage] = explode('|', trim($line));
        $result[$value] = $percentage;
      }
    }

    return $result;
  }

}
