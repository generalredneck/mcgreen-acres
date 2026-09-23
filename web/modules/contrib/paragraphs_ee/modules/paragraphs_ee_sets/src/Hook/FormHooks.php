<?php

declare(strict_types=1);

namespace Drupal\paragraphs_ee_sets\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Template\Attribute;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
use Drupal\paragraphs_sets\Entity\ParagraphsSet;
use Drupal\paragraphs_sets\ParagraphsSets;

/**
 * Hook implementations related to forms.
 */
class FormHooks {

  /**
   * Implements hook_paragraphs_ee_widget_access().
   *
   * @phpstan-param array<string, mixed> $elements
   * @phpstan-param array<string, mixed> $context
   */
  #[Hook('paragraphs_ee_widget_access')]
  public function widgetAccess(array $elements, FormStateInterface $form_state, array $context): AccessResultInterface {
    /** @var array<string, mixed> $add_more */
    $add_more = $elements['add_more'] ?? [];
    if (isset($add_more['#theme']) && ('paragraphs_sets_add_dialog' !== $add_more['#theme'])) {
      return AccessResult::neutral('Do not override default theme implementation for "add-more" element which is not "paragraphs_sets_add_dialog".');
    }
    return AccessResult::allowed();
  }

  /**
   * Implements hook_field_widget_complete_form_alter().
   *
   * @phpstan-param array<string, mixed> $field_widget_complete_form
   * @phpstan-param array<string, mixed> $context
   */
  #[Hook(
    hook: 'field_widget_complete_form_alter',
    order: Order::Last,
  )]
  public function fieldWidgetCompleteFormAlter(array &$field_widget_complete_form, FormStateInterface $form_state, array $context): void {
    $widget = $context['widget'];
    if (!($widget instanceof ParagraphsWidget && !empty($field_widget_complete_form['widget']))) {
      return;
    }

    /** @var array<string, mixed> $elements */
    $elements = &$field_widget_complete_form['widget'];
    // Load third-party settings.
    $widget_third_party_settings = (array) $widget->getThirdPartySetting('paragraphs_sets', 'paragraphs_sets', []);
    if (empty($widget_third_party_settings['use_paragraphs_sets'])) {
      return;
    }

    /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $items */
    $items = $context['items'];
    $field_definition = $items->getFieldDefinition();
    // Get a list of all Paragraphs types allowed in this field.
    $field_allowed_paragraphs_types = $widget->getAllowedTypes($field_definition);
    $sets = ParagraphsSets::getSets(array_keys($field_allowed_paragraphs_types));

    // Limit available sets from widget settings.
    /** @var array<int|string, mixed> $sets_allowed */
    $sets_allowed = $widget_third_party_settings['sets_allowed'] ?? [];
    if (count(array_filter($sets_allowed))) {
      $sets = array_intersect_key($sets, array_filter($sets_allowed));
    }

    if (!isset($elements['add_more'])) {
      return;
    }
    /** @var array<string, mixed> $add_more */
    $add_more = &$elements['add_more'];

    if ($widget->getSetting('add_mode') === 'modal') {
      // Make sure to use the correct theme for the modal dialog.
      $add_more['#theme'] = 'paragraphs_add_dialog__categorized';
    }

    foreach (array_keys($sets) as $key) {
      if (empty($add_more["append_selection_button_{$key}"])) {
        continue;
      }
      /** @var \Drupal\paragraphs_sets\ParagraphsSetInterface|null $set */
      $set = ParagraphsSet::load($key);
      if ($set === NULL) {
        continue;
      }
      /** @var array<string, mixed> $button */
      $button = $add_more["append_selection_button_{$key}"];
      // Use custom button layout.
      $button['#theme_wrappers'] = ['input__submit__paragraph_action__image'];
      /** @var array{class?: array<int, string>, ...} $button_attributes */
      $button_attributes = $button['#attributes'] ?? [];
      $button_attributes['class'][] = 'paragraphs-button--add-more';

      $button['#description'] = $set->getDescription();

      $icon_attributes = new Attribute();
      $icon_attributes['aria-hidden'] = 'true';
      $icon_attributes['class'] = ['paragraphs-button--icon'];
      if ($icon_url = $set->getIconUrl()) {
        // Extract icon from button.
        $button_attributes['class'][] = 'icon';
        unset($button_attributes['style']);
        $button['#icon'] = $icon_url;
      }
      else {
        $icon_attributes['class'][] = 'image-default';
      }
      $button['#attributes'] = $button_attributes;
      $button['#icon_attributes'] = $icon_attributes;
      $add_more["append_selection_button_{$key}"] = $button;
    }
  }

}
