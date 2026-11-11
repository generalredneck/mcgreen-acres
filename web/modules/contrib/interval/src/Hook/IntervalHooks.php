<?php

namespace Drupal\interval\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for interval.
 */
class IntervalHooks {
  /**
   * @file
   * Defines an interval field.
   * @copyright Copyright(c) 2011 Rowlands Group
   * @license GPL v2+ http://www.fsf.org/licensing/licenses/gpl.html
   */

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public static function theme() {
    $hooks = [
      'interval' => [
        'render element' => 'element',
        'template' => 'interval',
        'initial preprocess' => [self::class, 'preprocessInterval'],
      ],
    ];
    return $hooks;
  }

  /**
   * Implements template_preprocess_HOOK().
   */
  public static function preprocessInterval(array &$variables): void {
    $element = $variables['element'];

    $variables['attributes'] = [];
    if (isset($element['#id'])) {
      $variables['attributes']['id'] = $element['#id'];
    }
    if (!empty($element['#attributes']['class'])) {
      $variables['attributes']['class'] = (array) $element['#attributes']['class'];
    }
    $variables['attributes']['class'][] = 'container-inline';
    $variables['children'] = \Drupal::service('renderer')->render($element);
  }

}
