<?php

declare(strict_types=1);

namespace Drupal\recipe_tracker;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a log entity type.
 */
interface LogInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Set recipe name.
   *
   * @param string $recipe
   *   The user-facing name of the recipe from recipe.yml.
   *
   * @return self
   */
  public function setRecipeName(string $recipe): self;

  /**
   * Set fully-qualified package name.
   *
   * This name must come from composer.json file to uniqualy address the recipe.
   * In case the recipe is part of Drupal Core the value must be drupal/core.
   *
   * @param string $packageName
   *   The fully qualified composer package name.
   *
   * @return self
   */
  public function setPackageName(string $packageName): self;

  /**
   * Set recipe version.
   *
   * @param string $version
   *   The version.
   *
   * @return self
   */
  public function setVersion(string $version): self;
}
