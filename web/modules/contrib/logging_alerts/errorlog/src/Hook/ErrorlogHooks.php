<?php

namespace Drupal\errorlog\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for errorlog.
 */
class ErrorlogHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'errorlog.configuration':
        return '<p>' . $this->t("Sends logs and alerts to the web server's error log.") . '</p>';
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'errorlog_format' => [
        'variables' => [
          'log' => NULL,
        ],
        'initial preprocess' => 'template_preprocess_errorlog_format',
      ],
    ];
  }

}
