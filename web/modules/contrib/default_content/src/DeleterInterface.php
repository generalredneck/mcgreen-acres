<?php

namespace Drupal\default_content;

/**
 * An interface defining the default content deleter.
 */
interface DeleterInterface {

  /**
   * Delete default content from a given module.
   *
   * @param string $module
   *   The module to delete the default content from.
   */
  public function deleteModuleContent($module);

}
