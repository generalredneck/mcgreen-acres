<?php

namespace Drupal\commerce_shipping\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the interface for package type configuration entities.
 */
interface PackageTypeInterface extends ConfigEntityInterface {

  /**
   * Gets the package type dimensions.
   *
   * @return array|null
   *   An array with the following keys: length, width, height, unit.
   */
  public function getDimensions(): ?array;

  /**
   * Sets the package type dimensions.
   *
   * @param array $dimensions
   *   An array with the following keys: length, width, height, unit.
   *
   * @return $this
   */
  public function setDimensions(array $dimensions): static;

  /**
   * Gets the package type weight.
   *
   * This is the weight of an empty package.
   *
   * @return array
   *   An array with the following keys: number, unit.
   */
  public function getWeight(): ?array;

  /**
   * Sets the package type weight.
   *
   * @param array $weight
   *   An array with the following keys: number, unit.
   *
   * @return $this
   */
  public function setWeight(array $weight): static;

}
