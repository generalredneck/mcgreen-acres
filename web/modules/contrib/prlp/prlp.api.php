<?php

/**
 * @file
 * Hooks related to Password Reset Landing Page module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allow modules to alter the destination url for users during login.
 *
 * @param string $login_destination
 *   The login destination for the user to alter.
 */
function hook_prlp_login_destination_alter(string &$login_destination): void {
  // Redirect the user to their account page.
  $login_destination = '/user';
}

/**
 * @} End of "addtogroup hooks".
 */
