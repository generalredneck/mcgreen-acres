<?php

declare(strict_types=1);

namespace Drupal\paragraphs_ee\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
use Drupal\paragraphs_ee\Entity\ParagraphsCategory;
use Drupal\paragraphs_ee\ParagraphsEE;

/**
 * Hook implementations related to forms.
 */
class FormHooks {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Constructs a new FormHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {

  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   *
   * @phpstan-param array{paragraphs_categories: array<string, mixed>, '#entity_builders': array<mixed>} $form
   *   The form.
   */
  #[Hook('form_paragraphs_type_form_alter')]
  public function paragraphsTypeFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\paragraphs\ParagraphsTypeInterface $paragraph */
    $paragraph = $form_object->getEntity();

    /** @var \Drupal\paragraphs_ee\ParagraphsCategoryInterface[] $categories */
    $categories = $this->entityTypeManager->getStorage('paragraphs_category')
      ->loadMultiple();
    // Sort the entities using the entity class's sort() method.
    // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
    uasort($categories, [ParagraphsCategory::class, 'sort']);
    /** @var string[] $category_ids */
    $category_ids = array_column($categories, 'id');
    /** @var string[] $category_labels */
    $category_labels = array_column($categories, 'label');

    $form['paragraphs_categories'] = [
      '#type' => 'checkboxes',
      '#options' => array_combine($category_ids, $category_labels),
      '#title' => $this->t('Paragraphs categories'),
      '#description' => $this->t('Select all categories the paragraph applies to.'),
      '#default_value' => $paragraph->getThirdPartySetting('paragraphs_ee', 'paragraphs_categories', []),
    ];

    $form['#entity_builders'][] = [$this, 'paragraphsTypeFormBuilder'];
  }

  /**
   * Entity builder for the paragraphs_type configuration entity.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param \Drupal\paragraphs\ParagraphsTypeInterface $paragraph
   *   The paragraph to build the form for.
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   */
  public function paragraphsTypeFormBuilder(string $entity_type, ParagraphsTypeInterface $paragraph, array &$form, FormStateInterface $form_state): void {
    /** @var array<string, string> $categories */
    $categories = $form_state->getValue('paragraphs_categories', []);
    if (count($categories) > 0) {
      $paragraph->setThirdPartySetting('paragraphs_ee', 'paragraphs_categories', array_filter($categories));
      return;
    }

    // Remove setting.
    $paragraph->unsetThirdPartySetting('paragraphs_ee', 'paragraphs_categories');
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   *
   * @phpstan-param array{'#attached': array<string, array<string|int, mixed>>} $element
   * @phpstan-param array{widget?: \Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget|mixed, ...} $context
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface &$form_state, array $context): void {
    if (!isset($context['widget']) || (!$context['widget'] instanceof ParagraphsWidget)) {
      return;
    }
    // Add custom library.
    $element['#attached']['library'][] = 'paragraphs_ee/paragraphs_ee.paragraphs';
    if (empty($element['#attached']['drupalSettings']['paragraphs_features'])) {
      // If the widget is not configured to use one of the features provided by
      // the module, this would not exist and cause an error.
      $element['#attached']['drupalSettings']['paragraphs_features'] = [];
    }
  }

  /**
   * Implements hook_field_widget_complete_form_alter().
   *
   * @phpstan-param array<string, mixed> $field_widget_complete_form
   * @phpstan-param array<string, mixed> $context
   */
  #[Hook('field_widget_complete_form_alter')]
  public function fieldWidgetCompleteFormAlter(array &$field_widget_complete_form, FormStateInterface $form_state, array $context): void {
    $widget = $context['widget'];
    if (!($widget instanceof ParagraphsWidget && !empty($field_widget_complete_form['widget']))) {
      return;
    }

    /** @var array<string, mixed> $elements */
    $elements = &$field_widget_complete_form['widget'];

    // Check if modifications to widget are allowed.
    $hook_arguments = [$elements, $form_state, $context];
    $access_results = $this->moduleHandler->invokeAll('paragraphs_ee_widget_access', $hook_arguments);
    $result = AccessResult::neutral();
    if (!empty($access_results)) {
      /** @var \Drupal\Core\Access\AccessResultInterface $result */
      $result = array_shift($access_results);
      foreach ($access_results as $access_result) {
        /** @var \Drupal\Core\Access\AccessResultInterface $access_result */
        $result = $result->orIf($access_result);
      }
    }
    if ($result->isForbidden()) {
      return;
    }

    // Load all available paragraph types.
    $types_available = [];
    if (isset($elements['add_more'])) {
      /** @var array<string, mixed> $add_more */
      $add_more = &$elements['add_more'];
      $types_available = ParagraphsType::loadMultiple(array_column($add_more, '#bundle_machine_name'));
    }

    /** @var array<int, string> $visible_children */
    $visible_children = Element::getVisibleChildren($elements);
    foreach ($visible_children as $child_key) {
      /** @var array<string, mixed> $child */
      $child = &$elements[$child_key];
      /** @var string $paragraph_type_id */
      $paragraph_type_id = $child['#paragraph_type'] ?? '';
      if (!isset($types_available[$paragraph_type_id]) || !isset($child['top']) || !is_array($child['top'])) {
        continue;
      }
      /** @var array<string, mixed> $child_top */
      $child_top = &$child['top'];
      if (!isset($child_top['type']) || !is_array($child_top['type'])) {
        continue;
      }
      /** @var array<string, mixed> $child_top_type */
      $child_top_type = &$child_top['type'];
      if (!isset($child_top_type['label']) || !is_array($child_top_type['label'])) {
        continue;
      }
      /** @var array<string, mixed> $child_top_type_label */
      $child_top_type_label = &$child_top_type['label'];
      if (isset($child_top_type_label['#markup'])) {
        // Use translated bundle label.
        $child_top_type_label['#markup'] = '<span class="paragraph-type-label">' . $types_available[$paragraph_type_id]->label() . '</span>';
      }
    }

    // Add support for the modal dialog.
    if (($widget->getSetting('add_mode') === 'modal') && isset($elements['add_more'])) {
      /** @var array<string, mixed> $add_more */
      $add_more = &$elements['add_more'];
      /** @var array{library?: array<int, string>, drupalSettings?: array<string, array<string, mixed>>} $attached */
      $attached = &$elements['#attached'];

      if ($form_state->get('paragraphs_ee-add_mode') === 'off_canvas') {
        unset($add_more['add_modal_form_area']);
      }

      /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $items */
      $items = $context['items'];

      // Add custom library.
      $attached['library'][] = 'paragraphs_ee/paragraphs_ee.paragraphs';

      /** @var array{target_bundles_drag_drop?: array<int|string, array<string, mixed>>} $field_definition_settings */
      $field_definition_settings = (array) $items->getFieldDefinition()->getSetting('handler_settings');

      // Load all existing paragraph categories to validate references.
      $existing_category_ids = array_keys($this->entityTypeManager->getStorage('paragraphs_category')->loadMultiple());

      $buttons_ref = [];
      /** @var \Drupal\paragraphs\Entity\ParagraphsType $type */
      foreach ($types_available as $id => $type) {
        $button_id = "add_more_button_{$id}";
        /** @var array<string, mixed> $button */
        $button = $add_more[$button_id];

        // Use custom button layout (and rewrite <input> to <button>).
        $button['#theme_wrappers'] = ['input__submit__paragraph_action__image'];
        /** @var array{class?: array<int, string>, ...} $button_attributes */
        $button_attributes = &$button['#attributes'];
        $button_attributes['class'][] = 'paragraphs-button--add-more';

        // Set translated label as value.
        $button['#value'] = $type->label();
        // Set button description.
        $button['#description'] = $type->getDescription();

        $icon_attributes = new Attribute();
        $icon_attributes['aria-hidden'] = 'true';
        $icon_attributes['class'] = ['paragraphs-button--icon'];
        if ($icon_url = $type->getIconUrl()) {
          // Extract icon from button.
          $button_attributes['class'][] = 'icon';
          unset($button_attributes['style']);
          $button['#icon'] = $icon_url;
        }
        else {
          $icon_attributes['class'][] = 'image-default';
        }
        $button['#icon_attributes'] = $icon_attributes;
        $button['#weight'] = 0;
        if (isset($field_definition_settings['target_bundles_drag_drop'][$id]['weight'])) {
          $button['#weight'] = $field_definition_settings['target_bundles_drag_drop'][$id]['weight'];
        }
        $add_more[$button_id] = $button;

        /** @var array<int|string, string> $settings */
        $settings = array_filter((array) $type->getThirdPartySetting('paragraphs_ee', 'paragraphs_categories', []));
        // Remove settings for non-existing categories.
        $settings = array_intersect($settings, $existing_category_ids);
        if (empty($settings)) {
          // Store category for later use.
          $button['#paragraphs_category'] = '_none';
          $add_more[$button_id] = $button;
          // Paragraph type is uncategorized so we do not need to process it.
          continue;
        }
        // Call this first because the value is changed on second run only.
        Html::getUniqueId($button_id);
        $primary_category_assigned = FALSE;
        foreach ($settings as $paragraphs_category) {
          $buttons_ref[$button_id] = empty($buttons_ref[$button_id]) ? 1 : ($buttons_ref[$button_id] + 1);
          if (!$primary_category_assigned) {
            // Store category for later use.
            $button['#paragraphs_category'] = $paragraphs_category;
            $add_more[$button_id] = $button;
            $primary_category_assigned = TRUE;
            continue;
          }

          // Clone button and change some attributes so AJAX is working.
          /** @var string $current_button_id */
          $current_button_id = $button['#id'];
          $button['#id'] = Html::getUniqueId($current_button_id);
          $button_new_id = strtr(Html::getUniqueId($button_id), ['-' => '_']);
          /** @var string $current_button_name */
          $current_button_name = $button['#name'];
          $button['#name'] = $current_button_name . '__' . $buttons_ref[$button_id];
          $button_attributes['data-paragraphs-ee-button-clone'] = '';
          // Add clone of button to add_more-element.
          $add_more[$button_new_id] = $button;
          // Store category for later use.
          $add_more[$button_new_id]['#paragraphs_category'] = $paragraphs_category;
        }
      }

      uasort($add_more, function ($a, $b): int {
        /** @var array<string, mixed> $a */
        /** @var array<string, mixed> $b */
        return SortArray::sortByWeightProperty($a, $b);
      });

      $widget_third_party_settings = (array) $widget->getThirdPartySetting('paragraphs_ee', 'paragraphs_ee', []);

      $easy_access_buttons = [];
      $easy_access_count = $widget->getThirdPartySetting('paragraphs_features', 'add_in_between_link_count', 3);
      // Mark the first unique buttons for easy access.
      /** @var array<int, string> $add_more_children */
      $add_more_children = Element::children($add_more);
      foreach ($add_more_children as $child_key) {
        /** @var array<string, mixed> $add_more_child */
        $add_more_child = $add_more[$child_key];
        if (empty($add_more_child['#bundle_machine_name'])) {
          continue;
        }
        /** @var string $bundle_machine_name */
        $bundle_machine_name = $add_more_child['#bundle_machine_name'];
        if (count($easy_access_buttons) >= $easy_access_count) {
          // No need to process more elements as we reached the limit already.
          break;
        }
        if (isset($easy_access_buttons[$bundle_machine_name])) {
          // Button is already in list and is not added again.
          continue;
        }
        $easy_access_buttons[$bundle_machine_name] = $add_more_child['#weight'];
        $add_more_child['#easy_access'] = TRUE;
        /** @var array<string, mixed> $add_more_child_attributes */
        $add_more_child_attributes = $add_more_child['#attributes'] ?? [];
        $add_more_child_attributes['data-easy-access-weight'] = $add_more_child['#weight'];
        $add_more_child_attributes['data-paragraph-bundle'] = $bundle_machine_name;
        $add_more_child['#attributes'] = $add_more_child_attributes;
        $add_more[$child_key] = $add_more_child;
      }

      $attached['drupalSettings']['paragraphs_ee']['dialog_style'] = 'tiles';
      // Use different theme for modal dialog.
      $add_more['#theme'] = 'paragraphs_add_dialog__categorized';

      $wrapper_attributes = new Attribute();
      $wrapper_attributes['class'] = [
        'paragraphs-ee-dialog-wrapper',
        'js-hide',
      ];

      $wrapper_attributes['data-sidebar-disabled'] = FALSE;
      if (!empty($widget_third_party_settings['sidebar_disabled'])) {
        $wrapper_attributes['class'][] = 'sidebar-hidden';
        $wrapper_attributes['data-sidebar-disabled'] = TRUE;
      }
      $add_more['#wrapper_attributes'] = $wrapper_attributes;

      $dialog_attributes = new Attribute();
      $dialog_attributes['class'] = [
        'clearfix',
        'paragraphs-add-dialog',
        'paragraphs-add-dialog--categorized',
      ];

      if (!empty($widget_third_party_settings['dialog_style']) && ('tiles' !== $widget_third_party_settings['dialog_style'])) {
        /** @var string $custom_dialog_style */
        $custom_dialog_style = $widget_third_party_settings['dialog_style'];
        $attached['drupalSettings']['paragraphs_ee']['dialog_style'] = $custom_dialog_style;
        $dialog_attributes['class'][] = 'paragraphs-style-' . $custom_dialog_style;
      }

      $dialog_attributes['role'] = 'dialog';
      $dialog_attributes['aria-modal'] = 'true';
      $dialog_attributes['aria-label'] = $this->t('Add @widget_title', ['@widget_title' => $widget->getSetting('title')], ['context' => 'Paragraphs Editor Enhancements']);
      $dialog_attributes['data-widget-title'] = $widget->getSetting('title');
      $dialog_attributes['data-widget-title-plural'] = $widget->getSetting('title_plural');
      $dialog_attributes['data-paragraphs-ee-dialog-wrapper'] = '';

      if (!empty($widget_third_party_settings['dialog_off_canvas'])) {
        /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null $form_display */
        $form_display = $form_state->get('form_display');
        $dialog_attributes['data-dialog-off-canvas'] = 'true';
        $dialog_attributes['data-dialog-field-name'] = $items->getFieldDefinition()->getName();

        // Check if form_display is available before using it.
        if (!is_null($form_display)) {
          $browser_params = [
            'entity_type' => $form_display->getTargetEntityTypeId(),
            'bundle' => $form_display->getTargetBundle(),
            'form_mode' => $form_display->getMode(),
            'field_name' => $items->getFieldDefinition()->getName(),
          ];
          $dialog_attributes['data-dialog-browser-url'] = Url::fromRoute('paragraphs_ee.paragraphs_browser', $browser_params)->toString();

          /** @var array<string, mixed> $elements_dialog_attributes */
          $elements_dialog_attributes = $elements['#dialog_attributes'] ?? [];
          $elements_dialog_attributes['data-paragraphs-ee'] = '';
          $elements['#dialog_attributes'] = $elements_dialog_attributes;
        }
      }
      $add_more['#dialog_attributes'] = $dialog_attributes;

      /** @var array{library?: array<int, string>} $add_more_attached */
      $add_more_attached = &$add_more['#attached'];
      $add_more_attached['library'][] = 'paragraphs_ee/paragraphs_ee.categories';
    }

    // Add admin theme accent library.
    ParagraphsEE::addAdminThemeAccents($elements);

    // Register custom widget features.
    ParagraphsEE::registerWidgetFeatures($elements, $widget);
  }

}
