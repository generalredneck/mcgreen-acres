<?php

namespace Drupal\emaillog\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Logger class for emaillog module.
 */
class EmailLogger implements LoggerInterface {
  use RfcLoggerTrait;

  /**
   * A configuration object containing syslog settings.
   */
  protected ImmutableConfig $config;

  /**
   * Stores whether there is a system logger connection opened or not.
   */
  protected bool $connectionOpened = FALSE;

  /**
   * Constructs a SysLog object.
   *
   * The mail manager uses the logger.factory service. The logger.factory
   * service uses this logger. To prevent a circular dependency, this logger
   * gets the mail manager from a closure. The logger calls the closure only
   * when it sends an email.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LogMessageParserInterface $parser,
    protected LanguageManagerInterface $languageManager,
    protected StateInterface $state,
    protected ModuleHandlerInterface $moduleHandler,
    #[AutowireServiceClosure('plugin.manager.mail')]
    protected \Closure $mailManagerClosure,
  ) {
    $this->config = $configFactory->get('emaillog.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    // Do stuff.
    $language = $this->languageManager->getCurrentLanguage()->getId();

    // Send email only if there is an email address.
    // Otherwise the message is ignored by this module.
    if (!$to = $this->config->get('emaillog_' . $level)) {
      return;
    }

    // Send email only if the channel is allowed for this severity.
    if ($this->isChannelBlocked($level, $context)) {
      return;
    }

    // Assume that current message is not a repetition.
    $message_count = 1;

    // Check if main email repetition restricting options are set.
    // It's enough to check only emaillog_max_similar_emails variable,
    // as setting it requires emaillog_max_similarity_level to be set as well.
    // Saving few unnecessary database queries this way if it's not set.
    $max_similar_emails = $this->config->get('emaillog_max_similar_emails');
    if ($max_similar_emails) {
      $max_similarity_level = $this->config->get('emaillog_max_similarity_level');

      // Get previously sent message data
      // and compare its content with current one. If the module did not send
      // a message before, the default message is empty. An empty message is
      // not similar to the current message.
      $last_message = $this->state->get('emaillog.settings.emaillog_last_message', [
        'message' => '',
        'time' => 0,
        'count' => 0,
      ]);

      $max_length = max(strlen($message), strlen($last_message['message']));
      $similarity = 0;
      if ($max_length > 0) {
        similar_text($message, $last_message['message'], $similarity);
        $similarity /= 100;
      }

      // If similarity level is higher than allowed in module configuration,
      // and if maximum number of similar messages to sent was reached,
      // stop execution and return - no email should be sent in such case.
      if ($similarity > $max_similarity_level) {
        if ($last_message['count'] >= $max_similar_emails) {
          // Also make sure that those similar emails are consecutive,
          // ie. were sent during a specific period of time (if defined).
          $max_consecutive_timespan = $this->config->get('emaillog_max_consecutive_timespan');
          if (!$max_consecutive_timespan || $last_message['time'] >= time() - $max_consecutive_timespan * 60) {
            // No email should be sent - stop function execution.
            return;
          }
          // Reset last message count
          // if max consecutive time has already passed.
          $last_message['count'] = 0;
        }
        // Email should and will be sent, so increase counter for this message.
        $message_count = ++$last_message['count'];
      }
    }

    // Add additional debug info
    // (PHP predefined variables, debug backtrace etc.)
    $debug_info = [];
    $debug_info_settings = $this->config->get('emaillog_debug_info');

    foreach (_emaillog_get_debug_info_callbacks() as $debug_info_key => $debug_info_callback) {
      if (isset($debug_info_settings[$level][$debug_info_key]) && $debug_info_settings[$level][$debug_info_key]) {
        $debug_info[$debug_info_callback] = match ($debug_info_key) {
          'server' => $_SERVER,
          'env' => $_ENV,
          'request' => $_REQUEST,
          'cookie' => $_COOKIE,
          'get' => $_GET,
          'post' => $_POST,
          'session' => $_SESSION ?? NULL,
          'backtrace' => debug_backtrace(),
        };
      }
    }
    $this->moduleHandler->alter('emaillog_debug_info', $debug_info);

    $params = [
      'message' => $message,
      'severity' => $level,
      'variables' => $context,
      'debug_info' => $debug_info,
      'send' => TRUE,
    ];
    $this->moduleHandler->alter('emaillog_message', $params);

    // If a hook stops the email, do not update the similar email count.
    // The count includes only emails that the module sends.
    if (empty($params['send'])) {
      return;
    }

    // Make sure that $log['variables'] is always an array to avoid
    // errors like in issue http://drupal.org/node/1325938
    // Send email alert.
    $site_mail = $this->configFactory->get('system.site')->get('mail_notification');
    // If the custom site notification email has not been set, we use the site
    // default for this.
    if (empty($site_mail)) {
      $site_mail = $this->configFactory->get('system.site')->get('mail');
    }
    if (empty($site_mail)) {
      $site_mail = ini_get('sendmail_from');
    }

    ($this->mailManagerClosure)()->mail('emaillog', 'alert', $to, $language, $params, $site_mail);
    // Update email repetition restricting variables if needed.
    if ($max_similar_emails) {
      $last_message = [
        'message' => $message,
        'time'    => time(),
        'count'   => $message_count,
      ];
      $this->state->set('emaillog.settings.emaillog_last_message', $last_message);
    }
  }

  /**
   * Check if the channel is blocked for a specific severity.
   *
   * If this method returns TRUE, the logger does not send the email.
   */
  protected function isChannelBlocked(int $level, array $context): bool {
    $channels = $this->config->get('emaillog_' . $level . '_channels');
    if (empty($channels) || empty($context['channel'])) {
      return FALSE;
    }
    $exclude = FALSE;
    if (strpos($channels, '!') === 0) {
      $channels = substr($channels, 1);
      $exclude = TRUE;
    }
    $channels = explode(',', $channels);
    $channels = array_map('trim', $channels);
    $channel_found = in_array($context['channel'], $channels);
    if (($exclude && $channel_found) || (!$exclude && !$channel_found)) {
      return TRUE;
    }
    return FALSE;
  }

}
