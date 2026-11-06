<?php

/**
 * @file
 * Hooks that the Web Server Logging and Alerts module provides.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Changes or stops a message before the module writes it to the server log.
 *
 * The module invokes this hook only when the module settings select the
 * severity of the message.
 *
 * @param array $log
 *   The log entry for the errorlog_format theme hook. The array contains
 *   these keys:
 *   - level: The RFC 5424 severity level. See
 *     \Drupal\Core\Logger\RfcLogLevel.
 *   - context: The log context. It contains the placeholder values and the
 *     'channel', 'uid', 'ip', 'request_uri', 'referer' and 'link' keys.
 *   - message: The log message, before the module replaces its placeholders.
 *   - send: TRUE to write the message. Set this value to FALSE to stop the
 *     message.
 */
function hook_errorlog_message_alter(array &$log) {
  // Do not log failed logins. Another tool records them.
  if ($log['context']['channel'] === 'user' && str_starts_with($log['message'], 'Login attempt failed')) {
    $log['send'] = FALSE;
  }
}

/**
 * @} End of "addtogroup hooks".
 */
