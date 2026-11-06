<?php

namespace Drupal\webform_simplenews_handler\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for webform_simplenews_handler.
 */
class WebformSimplenewsHandlerHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the webform_simplenews_handler module.
      case 'help.page.webform_simplenews_handler':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Webform Simplenews Handler give the possibility to automatically subscribe to the newsletter.') . '</p>';
        return $output;

      default:
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    $info = [
      'webform_handler_submission_newsletter_summary' => [
        'variables' => ['settings' => NULL, 'handler' => NULL],
      ],
    ];

    return $info;
  }

}
