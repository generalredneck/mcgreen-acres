<?php

/**
 * @file
 * Hooks that the Email Logging and Alerts module provides.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Changes the debug info that the module attaches to an email alert.
 *
 * To change other parts of the alert, or to stop the alert, use
 * hook_emaillog_message_alter(). The module invokes that hook after this hook.
 *
 * @param array $debug_info
 *   The debug info. Each key is the name of the variable or function that
 *   supplied the value, for example '$_SERVER' or 'debug_backtrace()'. The
 *   array contains only the items that the module settings select for the
 *   severity of the message.
 */
function hook_emaillog_debug_info_alter(array &$debug_info) {
  // Remove the cookies from the email.
  unset($debug_info['$_COOKIE']);
}

/**
 * Changes or stops an email alert before the module sends it.
 *
 * The module invokes this hook only when the message passes these checks:
 * - The module settings have an email address for the severity.
 * - The channel filter for the severity lets the module send the message.
 * - The module did not send the maximum number of similar emails.
 *
 * When a hook stops an alert, the module does not count the alert for the
 * similar email limit.
 *
 * @param array $params
 *   The parameters for hook_mail(). The array contains these keys:
 *   - message: The log message, before the module replaces its placeholders.
 *   - severity: The RFC 5424 severity level. See
 *     \Drupal\Core\Logger\RfcLogLevel.
 *   - variables: The log context. It contains the placeholder values and the
 *     'channel', 'uid', 'link' and 'timestamp' keys.
 *   - debug_info: The debug info after hook_emaillog_debug_info_alter().
 *   - send: TRUE to send the email. Set this value to FALSE to stop the
 *     email.
 */
function hook_emaillog_message_alter(array &$params) {
  // Do not send alerts for missing WordPress pages.
  if ($params['variables']['channel'] === 'page not found' && str_starts_with($params['variables']['@uri'] ?? '', '/wp-')) {
    $params['send'] = FALSE;
  }
}

/**
 * @} End of "addtogroup hooks".
 */
