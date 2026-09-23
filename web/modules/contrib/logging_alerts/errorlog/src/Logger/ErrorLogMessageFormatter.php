<?php

namespace Drupal\errorlog\Logger;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Render\Renderer;
use Psr\Log\LoggerInterface;

/**
 * Logger class for errorlog module.
 */
class ErrorLogMessageFormatter implements LoggerInterface {
  use RfcLoggerTrait;

  /**
   * Configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Renderer object.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Constructs a SysLog object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The configuration factory object.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The Renderer object.
   */
  public function __construct(ConfigFactory $config_factory, Renderer $renderer) {
    $this->config = $config_factory->get('errorlog.settings');
    $this->renderer = $renderer;
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
      ];

      // Send themed alert to the web server's log.
      if (\Drupal::hasService('theme.manager')) {
        $errorlog_theme_element = [
          '#theme' => 'errorlog_format',
          '#log' => $log,
        ];
        $message = $this->renderer->renderInIsolation($errorlog_theme_element);;
      }

      error_log($message, 0);
    }
  }

}
