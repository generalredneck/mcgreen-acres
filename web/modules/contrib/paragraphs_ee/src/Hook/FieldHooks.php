<?php

declare(strict_types=1);

namespace Drupal\paragraphs_ee\Hook;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;

/**
 * Hook implementations related to fields.
 */
class FieldHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_field_widget_third_party_settings_form().
   *
   * @param \Drupal\Core\Field\WidgetInterface $plugin
   *   The widget plugin.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param string $form_mode
   *   Form display mode.
   * @param array<string|int, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array{paragraphs_ee?: array<string, mixed>}
   *   The third party settings form element structure.
   */
  #[Hook('field_widget_third_party_settings_form')]
  public function fieldWidgetThirdPartySettingsForm(WidgetInterface $plugin, FieldDefinitionInterface $field_definition, string $form_mode, array $form, FormStateInterface $form_state): array {
    $elements = [];

    if (!($plugin instanceof ParagraphsWidget)) {
      return $elements;
    }

    $settings_defaults = [
      'dialog_off_canvas' => FALSE,
      'dialog_style' => 'tiles',
    ];
    /** @var array{dialog_off_canvas?: bool, dialog_style?: string, drag_drop?: bool, sidebar_disabled?: bool} $settings */
    $settings = $plugin->getThirdPartySetting('paragraphs_ee', 'paragraphs_ee', $settings_defaults);
    // Define rule for enabling/disabling options that depend on modal add mode.
    $modal_related_options_rule = [
      ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][settings][add_mode]"]' => [
        'value' => 'modal',
      ],
    ];

    $elements['paragraphs_ee'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Paragraphs Editor Enhancements'),
      '#attributes' => [
        'class' => [
          'fieldgroup',
          'form-composite',
        ],
      ],
      '#weight' => 20,
    ];
    $elements['paragraphs_ee']['dialog_off_canvas'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use off-canvas instead of modal dialog'),
      '#default_value' => $settings['dialog_off_canvas'] ?? FALSE,
      '#attributes' => ['class' => ['paragraphs-ee__dialog-off-canvas__option']],
      '#states' => [
        'enabled' => $modal_related_options_rule,
        'visible' => $modal_related_options_rule,
      ],
    ];
    $elements['paragraphs_ee']['dialog_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Display Paragraphs in dialog as'),
      '#default_value' => $settings['dialog_style'] ?? 'tiles',
      '#attributes' => ['class' => ['paragraphs-ee__dialog-style__option']],
      '#options' => [
        'tiles' => $this->t('Tiles', [], ['context' => 'Paragraphs Editor Enhancements']),
        'list' => $this->t('List', [], ['context' => 'Paragraphs Editor Enhancements']),
      ],
      '#states' => [
        'enabled' => $modal_related_options_rule,
        'visible' => $modal_related_options_rule,
      ],
    ];

    $elements['paragraphs_ee']['drag_drop'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show arrows for drag & drop'),
      '#default_value' => $settings['drag_drop'] ?? FALSE,
    ];

    $elements['paragraphs_ee']['sidebar_disabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide sidebar in dialog'),
      '#default_value' => $settings['sidebar_disabled'] ?? FALSE,
    ];

    return $elements;
  }

  /**
   * Implements hook_field_widget_settings_summary_alter().
   *
   * @param string[]|\Drupal\Core\StringTranslation\TranslatableMarkup[] $summary
   *   The summary texts.
   * @param array{widget: object, ...} $context
   *   Given context.
   */
  #[Hook('field_widget_settings_summary_alter')]
  public function fieldWidgetSettingsSummaryAlter(array &$summary, array $context): void {
    $widget = $context['widget'];
    if (!($widget instanceof ParagraphsWidget)) {
      return;
    }

    if ($widget->getSetting('add_mode') !== 'modal') {
      return;
    }

    /** @var array{'paragraphs_ee': array<string, string|bool>} $settings */
    $settings = $widget->getThirdPartySettings('paragraphs_ee');
    if (isset($settings['paragraphs_ee']['dialog_off_canvas']) && ((bool)$settings['paragraphs_ee']['dialog_off_canvas'] === TRUE)) {
      $summary[] = $this->t('Use off-canvas dialog');
    }

    $styles = [
      'tiles' => $this->t('Tiles', [], ['context' => 'Paragraphs Editor Enhancements']),
      'list' => $this->t('List', [], ['context' => 'Paragraphs Editor Enhancements']),
    ];
    if (isset($settings['paragraphs_ee']['dialog_style']) && isset($styles[$settings['paragraphs_ee']['dialog_style']])) {
      $summary[] = $this->t('Display paragraphs in dialog as: %style', ['%style' => $styles[$settings['paragraphs_ee']['dialog_style']]], ['context' => 'Paragraphs Editor Enhancements']);
    }

    $easy_access_count = $widget->getThirdPartySetting('paragraphs_features', 'add_in_between_link_count', 3);
    $summary[] = $this->t('Number of add in between links: @count', ['@count' => $easy_access_count], ['context' => 'Paragraphs Editor Enhancements']);

    if (isset($settings['paragraphs_ee']['drag_drop']) && ($settings['paragraphs_ee']['drag_drop'] === TRUE)) {
      $summary[] = $this->t('Use arrows for drag & drop');
    }

    if (isset($settings['paragraphs_ee']['sidebar_disabled']) && ((bool)$settings['paragraphs_ee']['sidebar_disabled'] === TRUE) && (!isset($settings['paragraphs_ee']['dialog_off_canvas']) || ((bool)$settings['paragraphs_ee']['dialog_off_canvas'] === FALSE))) {
      $summary[] = $this->t('Hide sidebar');
    }
  }

}
