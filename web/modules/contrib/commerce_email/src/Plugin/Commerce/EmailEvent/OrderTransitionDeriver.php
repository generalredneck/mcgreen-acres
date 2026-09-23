<?php

namespace Drupal\commerce_email\Plugin\Commerce\EmailEvent;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives commerce_order transitions for the OrderTransition email event.
 */
class OrderTransitionDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The workflow manager.
   *
   * @var \Drupal\state_machine\WorkflowManagerInterface
   */
  protected $workflowManager;

  /**
   * The extension list module service.
   *
   * @var \Drupal\Core\Extension\ExtensionList
   */
  protected $extensionListModule;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = new self();
    $instance->workflowManager = $container->get('plugin.manager.workflow');
    $instance->stringTranslation = $container->get('string_translation');
    $instance->extensionListModule = $container->get('extension.list.module');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $derivative_definitions = [];
    $workflows = $this->workflowManager->getDefinitions();

    foreach ($workflows as $workflow) {
      if ($workflow['group'] !== 'commerce_order') {
        continue;
      }

      $extension = $this->extensionListModule->getExtensionInfo($workflow['provider']);
      foreach ($workflow['transitions'] as $id => $transition) {
        // The order place transition is already handled, skip it.
        if ($id === 'place' || $extension['type'] !== 'module') {
          continue;
        }
        $derivative_definitions[$id] = array_merge($base_plugin_definition, [
          'label' => $this->t('@label', ['@label' => $transition['label']]),
          'event_name' => "commerce_order.$id.post_transition",
          'group_name' => $this->t('Order workflow transitions'),
        ]);
      }
    }

    return $derivative_definitions;
  }

}
