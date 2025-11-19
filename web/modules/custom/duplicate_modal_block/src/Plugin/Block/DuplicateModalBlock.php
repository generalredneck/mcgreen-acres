<?php

namespace Drupal\duplicate_modal_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block that renders another block inside a modal.
 *
 * @Block(
 *   id = "duplicate_modal_block",
 *   admin_label = @Translation("Duplicate Block as Modal"),
 *   category = @Translation("Custom")
 * )
 */
class DuplicateModalBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, BlockManagerInterface $block_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->blockManager = $block_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.block')
    );
  }

  public function defaultConfiguration() {
    return [
      'target_block_id' => '',
    ];
  }

  public function blockForm($form, FormStateInterface $form_state) {
    $definitions = $this->blockManager->getDefinitions();
    $options = [];

    foreach ($definitions as $id => $definition) {
      $options[$id] = $definition['admin_label'] ?? $id;
    }

    $form['target_block_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Select an existing block to embed'),
      '#description' => $this->t('Choose a block plugin. If you enter a machine name below, this selection will be ignored.'),
      '#options' => $options,
      '#default_value' => $this->configuration['target_block_id'] ?? '',
    ];

    $form['manual_block_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Manual block machine name'),
      '#description' => $this->t('Optional. Enter a block machine name directly (e.g., mcgreen_acres_theme_webform). This overrides the dropdown above.'),
      '#default_value' => $this->configuration['manual_block_id'] ?? '',
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['target_block_id'] = $form_state->getValue('target_block_id');
    $this->configuration['manual_block_id'] = $form_state->getValue('manual_block_id');
  }

  public function build() {
    // Manual entry overrides dropdown selection.
    $manual = trim($this->configuration['manual_block_id'] ?? '');
    $selected = trim($this->configuration['target_block_id'] ?? '');

    $target = $manual !== '' ? $manual : $selected;

    if (!$target) {
      return ['#markup' => $this->t('No block selected or entered.')];
    }

    try {
      // Load block plugin instance.
      $plugin = $this->blockManager->createInstance($target, []);
      $content = $plugin->build();
    }
    catch (\Exception $e) {
      // Gracefully return error in markup instead of breaking the page.
      return [
        '#markup' => $this->t('Unable to load block: @id', ['@id' => $target]),
      ];
    }

    return [
      '#theme' => 'duplicate_modal_block',
      '#content' => $content,
      '#attached' => [
        'library' => [
          'duplicate_modal_block/modal_js',
          'duplicate_modal_block/modal_styles',
        ],
      ],
    ];
  }

}
