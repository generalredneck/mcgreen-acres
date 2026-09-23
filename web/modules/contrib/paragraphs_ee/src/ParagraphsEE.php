<?php

namespace Drupal\paragraphs_ee;

use Drupal\Core\Render\Element;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;

/**
 * Helper class for ParagraphsEE.
 */
class ParagraphsEE {

  /**
   * Register features for paragraphs field widget.
   *
   * @param array<string, mixed> $elements
   *   Render array for the field widget.
   * @param \Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget $widget
   *   Field widget object.
   */
  public static function registerWidgetFeatures(array &$elements, ParagraphsWidget $widget): void {
    /** @var array<string, mixed> $third_party_settings */
    $third_party_settings = (array) $widget->getThirdPartySetting('paragraphs_ee', 'paragraphs_ee', []);
    if (isset($third_party_settings['drag_drop']) && ($third_party_settings['drag_drop'] === TRUE)) {
      /** @var array{library?: array<int, string>, drupalSettings?: array<string, array<string, mixed>>} $attached */
      $attached = $elements['#attached'] ?? [];
      $attached['library'][] = 'paragraphs_ee/paragraphs_ee.drag_drop';
      $attached['drupalSettings']['paragraphs_ee']['widgetTitle'] = $widget->getSetting('title');
      $elements['#attached'] = $attached;

      // Use a copy so passing $elements by reference into Element::children()
      // cannot widen the type of the $elements parameter itself.
      $elements_copy = $elements;
      /** @var array<int, string> $children */
      $children = Element::children($elements_copy);
      foreach ($children as $key) {
        /** @var array<string, mixed> $child */
        $child = $elements[$key];
        /** @var array<string, mixed> $child_top */
        $child_top = $child['top'] ?? [];
        /** @var array{class?: array<int, string>, ...} $child_top_attributes */
        $child_top_attributes = $child_top['#attributes'] ?? [];
        $child_top_attributes['class'][] = 'drag-drop-buttons';
        $child_top['#attributes'] = $child_top_attributes;
        $child['top'] = $child_top;
        $elements[$key] = $child;
      }
    }
  }

  /**
   * Add admin theme accents to the widget.
   *
   * @param array<string, mixed> $elements
   *   Render array for the field widget.
   */
  public static function addAdminThemeAccents(array &$elements): void {
    $active_theme = \Drupal::theme()->getActiveTheme();
    $theme_name = $active_theme->getName();
    $base_theme_extensions = $active_theme->getBaseThemeExtensions();

    $library = NULL;
    if (($theme_name === 'gin') || isset($base_theme_extensions['gin'])) {
      $library = 'paragraphs_ee/paragraphs_ee.gin_accent';
    }
    elseif (($theme_name === 'default_admin') || isset($base_theme_extensions['default_admin'])) {
      $library = 'paragraphs_ee/paragraphs_ee.default_admin_accent';
    }

    if ($library !== NULL) {
      /** @var array<string, mixed> $add_more */
      $add_more = $elements['add_more'] ?? [];
      /** @var array{library?: array<int, string>} $add_more_attached */
      $add_more_attached = $add_more['#attached'] ?? [];
      $add_more_attached['library'][] = $library;
      $add_more['#attached'] = $add_more_attached;
      $elements['add_more'] = $add_more;
    }
  }

}
