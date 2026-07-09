<?php

namespace Drupal\mcgreen_acres_store\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a single field off the referenced entity, not the whole entity.
 *
 * Unlike the core "Rendered entity" formatter, this never calls the
 * referenced entity's view builder, so it can't trigger any alterBuild()
 * logic on that entity type (e.g. commerce_product's variation field
 * injection), which avoids recursive-render collisions when the referencing
 * entity is itself embedded in that injection (e.g. a product variation's
 * own display rendering its parent product).
 */
#[FieldFormatter(
  id: 'mcgreen_acres_store_entity_reference_field_view',
  label: new TranslatableMarkup('Rendered field of referenced entity'),
  description: new TranslatableMarkup('Render a single field from the referenced entity, without rendering the whole entity.'),
  field_types: [
    'entity_reference',
  ],
)]
class EntityReferenceFieldViewFormatter extends EntityReferenceFormatterBase {

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'field_name' => 'images',
      'view_mode' => 'default',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $target_type = $this->getFieldSetting('target_type');

    $elements['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field name'),
      '#description' => $this->t('The machine name of the field to render from the referenced entity.'),
      '#default_value' => $this->getSetting('field_name'),
      '#required' => TRUE,
    ];
    $elements['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('View mode'),
      '#description' => $this->t('Used to look up the display settings for the field above.'),
      '#options' => $this->entityDisplayRepository->getViewModeOptions($target_type),
      '#default_value' => $this->getSetting('view_mode'),
      '#required' => TRUE,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Field: @field_name', ['@field_name' => $this->getSetting('field_name')]);
    $summary[] = $this->t('View mode: @view_mode', ['@view_mode' => $this->getSetting('view_mode')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $field_name = $this->getSetting('field_name');
    $view_mode = $this->getSetting('view_mode');
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      if ($entity->hasField($field_name)) {
        $elements[$delta] = $entity->get($field_name)->view($view_mode);
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    return \Drupal::entityTypeManager()->getDefinition($target_type)->hasHandlerClass('view_builder');
  }

}
