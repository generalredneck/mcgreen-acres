<?php

namespace Drupal\default_content\Commands;

use Drupal\default_content\DeleterInterface;
use Drupal\default_content\ImporterInterface;
use Drush\Commands\DrushCommands;

/**
 * Deletes and/or import module content.
 *
 * @package Drupal\default_content
 */
class DefaultContentDeleteCommands extends DrushCommands {

  /**
   * The default content deleter.
   *
   * @var \Drupal\default_content\DeleterInterface
   */
  protected DeleterInterface $defaultContentDeleter;

  /**
   * The default content importer.
   *
   * @var \Drupal\default_content\ImporterInterface
   */
  protected ImporterInterface $defaultContentImporter;

  /**
   * DefaultContentDeleteCommands constructor.
   *
   * @param \Drupal\default_content\DeleterInterface $default_content_deleter
   *   The default content deleter.
   * @param \Drupal\default_content\ImporterInterface $default_content_importer
   *   The default content importer.
   */
  public function __construct(DeleterInterface $default_content_deleter, ImporterInterface $default_content_importer) {
    parent::__construct();
    $this->defaultContentDeleter = $default_content_deleter;
    $this->defaultContentImporter = $default_content_importer;
  }

  /**
   * Deletes all the content defined in a module's content folder.
   *
   * @param string $module
   *   The name of the module.
   *
   * @command default-content:delete-module
   * @aliases dcdm
   */
  public function contentDeleteModule($module) {
    $this->defaultContentDeleter->deleteModuleContent($module);
  }

  /**
   * Deletes the content defined in a module's content and imports it again.
   *
   * @param string $module
   *   The name of the module.
   *
   * @command default-content:reimport-module
   * @aliases dcrm
   */
  public function contentReimportModule($module) {
    $this->logger()->notice('Deleting default content from module ' . $module);
    $this->defaultContentDeleter->deleteModuleContent($module);
    $this->logger()->notice('Reimporting default content.');
    $this->defaultContentImporter->importContent($module);
  }

}
