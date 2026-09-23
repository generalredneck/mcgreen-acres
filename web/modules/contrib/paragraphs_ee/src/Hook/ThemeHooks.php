<?php

declare(strict_types=1);

namespace Drupal\paragraphs_ee\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs_ee\Entity\ParagraphsCategory;

/**
 * Hook implementations for theming.
 */
class ThemeHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ModuleExtensionList $moduleExtensionList,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    // Nothing to do here.
  }

  /**
   * Implements hook_theme().
   *
   * @phpstan-return array<string, array<string, string>>
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraphs_add_dialog__categorized' => [
        'render element' => 'element',
        'template' => 'paragraphs-add-dialog--categorized',
        'initial preprocess' => static::class . ':preprocessParagraphsAddDialogCategorized',
      ],
      'input__submit__paragraph_action__image' => [
        'base hook' => 'input',
        'render element' => 'element',
        'template' => 'input--submit--paragraph_action--image',
        'path' => $this->moduleExtensionList->getPath('paragraphs_ee') . '/templates',
      ],
    ];
  }

  /**
   * Prepare variables used in paragraphs-add-dialog--categorized.html.twig.
   *
   * @param array<string, mixed> $variables
   *   An associative array containing variables used in the template.
   */
  public function preprocessParagraphsAddDialogCategorized(array &$variables): void {
    // Define variables for the template.
    $variables += ['buttons' => []];

    /** @var array<string, mixed> $element */
    $element = &$variables['element'];

    $variables['add_mode'] = $element['#add_mode'] ?? 'modal';
    $variables['add'] = [];
    if (isset($element['add_modal_form_area'])) {
      $variables['add']['add_modal_form_area'] = $element['add_modal_form_area'];
    }
    if (isset($element['add_more_delta'])) {
      $variables['add']['add_more_delta'] = $element['add_more_delta'];
    }
    /** @var \Drupal\paragraphs_ee\ParagraphsCategoryInterface[] $paragraphs_categories */
    $paragraphs_categories = $this->entityTypeManager->getStorage('paragraphs_category')
      ->loadMultiple();
    // Sort the entities using the entity class's sort() method.
    // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
    uasort($paragraphs_categories, [ParagraphsCategory::class, 'sort']);

    /** @var \Drupal\Core\Template\Attribute $dialog_attributes */
    $dialog_attributes = $element['#dialog_attributes'];

    $category_translation_arguments = [
      '@title' => $dialog_attributes['data-widget-title'],
      '@title_plural' => $dialog_attributes['data-widget-title-plural'],
    ];

    /** @var array<string, array<string, mixed>> $categories */
    $categories = [];
    $categories['_all'] = [
      'title' => $this->t('All'),
      'link_title' => $this->t('Show all @title_plural', $category_translation_arguments),
      'description' => '',
      'id' => 'all',
    ];

    $category_names = array_keys($paragraphs_categories);
    /** @var array<string, array<int, array<string, mixed>>> $grouped */
    $grouped = array_combine($category_names, array_fill(0, count($category_names), []));
    /** @var \Drupal\paragraphs_ee\ParagraphsCategoryInterface $category */
    foreach ($paragraphs_categories as $key => $category) {
      if (isset($element['#id'])) {
        /** @var string $element_id */
        $element_id = $element['#id'];
        $conditional_id = $element_id . '-category-' . $key;
      }
      else {
        $conditional_id = 'category-' . $key;
      }
      $category_translation_arguments['@category_title'] = $category->label();
      $categories[$key] = [
        'title' => $category->label(),
        'link_title' => $this->t('Show @category_title only', $category_translation_arguments),
        'description' => $category->getDescription(),
        'id' => Html::getUniqueId($conditional_id),
      ];
    }
    $categories['_none'] = [
      'title' => $this->t('Uncategorized'),
      'link_title' => $this->t('Show uncategorized @title_plural', $category_translation_arguments),
      'description' => '',
      'id' => Html::getUniqueId('uncategorized'),
    ];
    // Add category for uncategorized items.
    $grouped['_none'] = [];
    /** @var array<int, string> $element_children */
    $element_children = Element::children($element);
    foreach ($element_children as $element_key) {
      /** @var array<string, mixed> $child */
      $child = $element[$element_key];
      if (empty($child['#paragraphs_category'])) {
        continue;
      }
      /** @var string $category_key */
      $category_key = $child['#paragraphs_category'];
      // Add button to category.
      $grouped[$category_key][] = $child;
    }
    $variables['wrapper_attributes'] = $element['#wrapper_attributes'];
    $variables['dialog_attributes'] = $dialog_attributes;
    $variables['groups'] = array_filter($grouped);

    // Remove all empty categories.
    $empty_categories = array_diff(array_keys($grouped), array_keys(array_filter($grouped)));
    if (count($empty_categories) > 0) {
      $categories = array_diff_key($categories, array_flip($empty_categories));
    }
    $variables['categories'] = $categories;

    $placeholder_value = 'Paragraphs';
    if (isset($dialog_attributes['data-widget-title-plural'])) {
      /** @var string $placeholder_value */
      $placeholder_value = $dialog_attributes['data-widget-title-plural'];
    }
    $variables['filter_placeholder'] = $this->t('Search', [], ['context' => 'Paragraphs Editor Enhancements']);
    $variables['filter_description'] = $this->t('Search @placeholder_value by title and description', ['@placeholder_value' => $placeholder_value], ['context' => 'Paragraphs Editor Enhancements']);
    /** @var array<string, mixed> $element_wrapper_attributes */
    $element_wrapper_attributes = $element['#wrapper_attributes'] ?? [];
    $variables['sidebar_disabled'] = $element_wrapper_attributes['data-sidebar-disabled'] ?? FALSE;
  }

  /**
   * Prepare variables used in input--submit--paragraph_action--image.html.twig.
   *
   * @param array<string, mixed> $variables
   *   An associative array containing variables used in the template.
   */
  #[Hook('preprocess_input__submit__paragraph_action__image')]
  public function preprocessInputSubmitParagraphActionImage(array &$variables): void {
    /** @var array<string, mixed> $element */
    $element = $variables['element'];

    // Add title and description as custom element.
    $variables['title'] = $element['#value'];
    $variables['description'] = $element['#description'];

    /** @var \Drupal\Core\Template\Attribute|array<string, mixed> $attributes */
    $attributes = $variables['attributes'] ?? [];
    $variables['label_id'] = empty($attributes['aria-labelledby']) ? NULL : $attributes['aria-labelledby'];
    $variables['description_id'] = empty($attributes['aria-describedby']) ? NULL : $attributes['aria-describedby'];

    /** @var \Drupal\Core\Template\Attribute|array<string, mixed> $icon_attributes */
    $icon_attributes = $element['#icon_attributes'];
    if (isset($element['#icon'])) {
      /** @var string $icon_src */
      $icon_src = $element['#icon'];
      $icon_attributes['style'] = 'background-image: url("' . $icon_src . '");';
    }
    $variables['icon_attributes'] = $icon_attributes;
  }

}
