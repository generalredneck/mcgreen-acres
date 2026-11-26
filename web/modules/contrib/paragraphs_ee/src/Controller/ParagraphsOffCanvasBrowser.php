<?php

namespace Drupal\paragraphs_ee\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Field\PluginSettingsInterface;
use Drupal\Core\Form\FormState;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;

/**
 * Controller for the Paragraphs off-canvas browser.
 */
class ParagraphsOffCanvasBrowser extends ControllerBase implements ParagraphsOffCanvasBrowserInterface {

  /**
   * The form display.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null
   */
  protected $formDisplay = NULL;

  /**
   * {@inheritdoc}
   */
  public function getTitle(string $entity_type, string $bundle, string $form_mode, string $field_name): string {
    $title_default = $this->t('Add Paragraph', [], ['context' => 'Paragraphs Editor Enhancements']);

    $component = $this->getComponent($entity_type, $bundle, $form_mode, $field_name);

    if (is_null($component) || !$this->hasDialogOffCanvas($component)) {
      return $title_default;
    }

    /** @var array<string, mixed> $component_settings */
    $component_settings = $component['settings'] ?? [];
    return $this->t('Add @widget_title', ['@widget_title' => $component_settings['title']], ['context' => 'Paragraphs Editor Enhancements']);
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The render array of the off-canvas content.
   */
  public function content(string $entity_type, string $bundle, string $form_mode, string $field_name): array {
    $build = [];

    $component = $this->getComponent($entity_type, $bundle, $form_mode, $field_name);

    if (is_null($component) || !$this->hasDialogOffCanvas($component)) {
      return $build;
    }

    // Load the paragraphs widget for the field.
    $widget = $this->getWidget($entity_type, $bundle, $form_mode, $field_name);
    if (!($widget instanceof ParagraphsWidget) || ('modal' !== $widget->getSetting('add_mode'))) {
      return $build;
    }

    $form = [
      '#parents' => [],
    ];
    $form_state = new FormState();
    // Get the bundle type name.
    $bundle_type = $this->entityTypeManager()->getDefinition($entity_type)->getKey('bundle');
    // Create an empty entity of type $entity_type and bundle $bundle.
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type)->create([
      $bundle_type => $bundle,
    ]);
    $form_state->set('entity', $entity);
    $form_state->set('paragraphs_ee-add_mode', 'off_canvas');
    $form_state->set('form_display', $this->getFormDisplay($entity_type, $bundle, $form_mode));
    $items = $entity->get($field_name);
    $items->filterEmptyItems();
    /** @var array<string, mixed> $widget_form */
    $widget_form = $widget->form($items, $form, $form_state);
    /** @var array<string, mixed> $widget_form_widget */
    $widget_form_widget = $widget_form['widget'] ?? [];

    /** @var array<string, mixed> $dialog */
    $dialog = $widget_form_widget['add_more'] ?? [];
    $dialog['#add'] = NULL;
    $dialog['#add_mode'] = 'off_canvas';
    $build['dialog'] = $dialog;

    /** @var array{library?: array<int, string>} $attached */
    $attached = [];
    $attached['library'][] = 'paragraphs_ee/paragraphs_ee.categories';
    $attached['library'][] = 'paragraphs_ee/paragraphs_ee.off_canvas';
    $build['#attached'] = $attached;

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormDisplay(string $entity_type, string $bundle, string $form_mode): ?EntityFormDisplayInterface {
    if (is_null($this->formDisplay)) {
      $this->formDisplay = $this->entityTypeManager()
        ->getStorage('entity_form_display')
        ->load($entity_type . '.' . $bundle . '.' . $form_mode);
    }

    return $this->formDisplay;
  }

  /**
   * {@inheritdoc}
   */
  public function getComponent(string $entity_type, string $bundle, string $form_mode, string $field_name): ?array {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null $form_display */
    $form_display = $this->getFormDisplay($entity_type, $bundle, $form_mode);
    if (is_null($form_display)) {
      return NULL;
    }

    $component = $form_display->getComponent($field_name);
    if (!$component || !$this->hasDialogOffCanvas($component)) {
      return NULL;
    }

    return $component;
  }

  /**
   * Checks whether a field display component has off-canvas dialog enabled.
   *
   * @param array<int|string, mixed> $component
   *   The field display component.
   *
   * @return bool
   *   TRUE if the "dialog_off_canvas" third party setting is enabled.
   */
  protected function hasDialogOffCanvas(array $component): bool {
    /** @var array<string, mixed> $third_party_settings */
    $third_party_settings = $component['third_party_settings'] ?? [];
    /** @var array<string, mixed> $paragraphs_ee_settings */
    $paragraphs_ee_settings = $third_party_settings['paragraphs_ee'] ?? [];
    /** @var array<string, mixed> $paragraphs_ee_nested_settings */
    $paragraphs_ee_nested_settings = $paragraphs_ee_settings['paragraphs_ee'] ?? [];
    return ($paragraphs_ee_nested_settings['dialog_off_canvas'] ?? FALSE) === TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidget(string $entity_type, string $bundle, string $form_mode, string $field_name): ?PluginSettingsInterface {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null $form_display */
    $form_display = $this->getFormDisplay($entity_type, $bundle, $form_mode);
    if (is_null($form_display)) {
      return NULL;
    }

    return $form_display->getRenderer($field_name);
  }

}
