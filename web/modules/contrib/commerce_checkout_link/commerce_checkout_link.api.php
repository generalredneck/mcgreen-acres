<?php

/**
 * @file
 * Api.php file for this.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Change the timeout for a checkout link.
 */
function hook_commerce_checkout_link_timeout_alter(&$timeout) {
  // Make the timeout 7 days instead of one.
  $timeout = $timeout * 7;
}

/**
 * @} End of "addtogroup hooks"
 */
