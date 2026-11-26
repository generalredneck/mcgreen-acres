<?php

namespace Drupal\errorlog\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Render\RendererInterface;
use Psr\Log\LoggerInterface;

/**
 * Logger class for errorlog module.
 */
class ErrorLogMessageFormatter implements LoggerInterface {
  use RfcLoggerTrait;

  /**
   * Configuration object.
   */
  protected ImmutableConfig $config;

  /**
   * Constructs a SysLog object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    protected RendererInterface $renderer,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    $this->config = $config_factory->get('errorlog.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    if ($this->config->get('errorlog_' . $level)) {

      $log = [
        'level' => $level,
        'context' => $context,
        'message' => $message,
        'send' => TRUE,
      ];
      $this->moduleHandler->alter('errorlog_message', $log);

      // Stop if a hook stopped the message.
      if (empty($log['send'])) {
        return;
      }
      $message = $log['message'];

      // Send themed alert to the web server's log. The logger can outlive its
      // container (e.g. during a rebuild or install), and rendering pulls
      // services from the current global container, so check that container
      // rather than an injected one.
      // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
      if (\Drupal::hasService('theme.manager')) {
        $errorlog_theme_element = [
          '#theme' => 'errorlog_format',
          '#log' => $log,
        ];
        $message = $this->renderer->renderInIsolation($errorlog_theme_element);
      }

      error_log($message, 0);
    }
  }

}
