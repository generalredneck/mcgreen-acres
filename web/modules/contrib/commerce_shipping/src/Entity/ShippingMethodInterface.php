<?php

namespace Drupal\commerce_shipping\Entity;

use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface as ShippingMethodPluginInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\commerce_store\Entity\EntityStoresInterface;

/**
 * Defines the interface for shipping methods.
 *
 * Stores configuration for shipping method plugins.
 * Implemented as a content entity type to allow each store to have its own
 * shipping methods.
 */
interface ShippingMethodInterface extends ContentEntityInterface, EntityStoresInterface, EntityChangedInterface {

  /**
   * Gets the shipping method plugin.
   *
   * @return \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface
   *   The shipping method plugin.
   */
  public function getPlugin(): ShippingMethodPluginInterface;

  /**
   * Gets the shipping method name.
   *
   * @return string
   *   The shipping method name.
   */
  public function getName(): ?string;

  /**
   * Sets the shipping method name.
   *
   * @param string $name
   *   The shipping method name.
   *
   * @return $this
   */
  public function setName(string $name): static;

  /**
   * Gets the shipping method conditions.
   *
   * @return \Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface[]
   *   The shipping method conditions.
   */
  public function getConditions(): array;

  /**
   * Sets the shipping method conditions.
   *
   * @param \Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface[] $conditions
   *   The conditions.
   *
   * @return $this
   */
  public function setConditions(array $conditions): static;

  /**
   * Gets the shipping method condition operator.
   *
   * @return string
   *   The condition operator. Possible values: AND, OR.
   */
  public function getConditionOperator(): string;

  /**
   * Sets the shipping method condition operator.
   *
   * @param string $condition_operator
   *   The condition operator.
   *
   * @return $this
   */
  public function setConditionOperator(string $condition_operator): static;

  /**
   * Gets the shipping method weight.
   *
   * @return int
   *   The shipping method weight.
   */
  public function getWeight(): int;

  /**
   * Sets the shipping method weight.
   *
   * @param int $weight
   *   The shipping method weight.
   *
   * @return $this
   */
  public function setWeight(int $weight): static;

  /**
   * Gets whether the shipping method is enabled.
   *
   * @return bool
   *   TRUE if the shipping method is enabled, FALSE otherwise.
   */
  public function isEnabled(): bool;

  /**
   * Sets whether the shipping method is enabled.
   *
   * @param bool $enabled
   *   Whether the shipping method is enabled.
   *
   * @return $this
   */
  public function setEnabled(bool $enabled): static;

  /**
   * Checks whether the shipping method applies to the given shipment.
   *
   * Ensures that the conditions pass.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return bool
   *   TRUE if shipping method applies, FALSE otherwise.
   */
  public function applies(ShipmentInterface $shipment): bool;

  /**
   * Gets the shipping method creation timestamp.
   *
   * @return int
   *   The shipping method creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the shipping method creation timestamp.
   *
   * @param int $timestamp
   *   The shipping method creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime(int $timestamp): static;

}
