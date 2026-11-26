<?php

namespace Drupal\paragraphs_ee\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\FilterFormatRepositoryInterface;
use Drupal\paragraphs_ee\Controller\ParagraphsCategoryListBuilder;
use Drupal\paragraphs_ee\Form\ParagraphsCategoryDeleteForm;
use Drupal\paragraphs_ee\Form\ParagraphsCategoryForm;
use Drupal\paragraphs_ee\ParagraphsCategoryInterface;

/**
 * Defines the Example entity.
 */
#[ConfigEntityType(
  id: 'paragraphs_category',
  label: new TranslatableMarkup('Paragraphs category'),
  config_prefix: 'paragraphs_category',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'weight' => 'weight',
  ],
  handlers: [
    'list_builder' => ParagraphsCategoryListBuilder::class,
    'form' => [
      'add' => ParagraphsCategoryForm::class,
      'edit' => ParagraphsCategoryForm::class,
      'delete' => ParagraphsCategoryDeleteForm::class,
    ],
  ],
  links: [
    'edit-form' => '/admin/structure/paragraphs_category/{paragraphs_category}',
    'delete-form' => '/admin/structure/paragraphs_category/{paragraphs_category}/delete',
  ],
  admin_permission: 'administer paragraphs categories',
  config_export: [
    'id',
    'label',
    'description',
    'weight',
  ],
)]
class ParagraphsCategory extends ConfigEntityBase implements ParagraphsCategoryInterface {

  /**
   * The category ID.
   *
   * @var string
   */
  public $id;

  /**
   * The category label.
   *
   * @var string
   */
  public $label;

  /**
   * The category description.
   *
   * @var array<string, string>|string|null
   */
  public $description;

  /**
   * The category weight.
   *
   * @var int
   */
  public $weight;

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    if (!is_array($this->description)) {
      // Rewrite to new structure.
      /** @var string $description_value */
      $description_value = $this->description ?? '';
      /** @var string $format_id */
      $format_id = \Drupal::service(FilterFormatRepositoryInterface::class)->getDefaultFormat()->id();
      $this->description = [
        'value' => $description_value,
        'format' => $format_id,
      ];
    }
    $build = [
      '#type' => 'processed_text',
      '#text' => $this->description['value'],
      '#format' => $this->getDescriptionFormat(),
    ];
    return (string) \Drupal::service(RendererInterface::class)->renderInIsolation($build);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescriptionFormat(): ?string {
    if (is_array($this->description) && isset($this->description['format'])) {
      /** @var string $format */
      $format = $this->description['format'];
      return $format;
    }
    /** @var string $format_id */
    $format_id = \Drupal::service(FilterFormatRepositoryInterface::class)->getDefaultFormat()->id();
    return $format_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    /** @var int $weight */
    $weight = $this->get('weight') ?? 0;
    return $weight;
  }

}
