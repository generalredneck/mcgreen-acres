<?php

namespace Drupal\commerce_tip;

/**
 * Commerce Tip Utilities Interface.
 */
interface CommerceTipUtilitiesInterface {

  /**
   * Converts string tip options config to an array.
   *
   * @param string $input
   *   The input string containing tip options.
   *
   * @return array
   *   An array of converted tip options.
   */
  public function convertTipOptions(string $input);

}
