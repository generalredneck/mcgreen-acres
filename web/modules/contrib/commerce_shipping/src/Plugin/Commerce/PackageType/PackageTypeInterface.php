<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\PackageType;

use Drupal\physical\Length;
use Drupal\physical\Weight;

/**
 * Defines the interface for package types.
 */
interface PackageTypeInterface {

  /**
   * Gets the package type ID.
   *
   * @return string
   *   The package type ID.
   */
  public function getId(): string;

  /**
   * Gets the package type remote ID.
   *
   * @return string
   *   The package type remote ID, or "custom" if the package type was not
   *   predefined by the remote API.
   */
  public function getRemoteId(): string;

  /**
   * Gets the translated label.
   *
   * @return string
   *   The translated label.
   */
  public function getLabel(): string;

  /**
   * Gets the package type length.
   *
   * @return \Drupal\physical\Length
   *   The package type length.
   */
  public function getLength(): Length;

  /**
   * Gets the package type width.
   *
   * @return \Drupal\physical\Length
   *   The package type width.
   */
  public function getWidth(): Length;

  /**
   * Gets the package type height.
   *
   * @return \Drupal\physical\Length
   *   The package type height.
   */
  public function getHeight(): Length;

  /**
   * Gets the package type weight.
   *
   * This is the weight of an empty package.
   *
   * @return \Drupal\physical\Weight
   *   The package type weight.
   */
  public function getWeight(): Weight;

}
