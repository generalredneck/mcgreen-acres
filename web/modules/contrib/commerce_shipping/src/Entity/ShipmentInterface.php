<?php

namespace Drupal\commerce_shipping\Entity;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\commerce_order\EntityAdjustableInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface as PackageTypePluginInterface;
use Drupal\commerce_shipping\ProposedShipment;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\physical\Weight;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;

/**
 * Defines the interface for shipments.
 */
interface ShipmentInterface extends ContentEntityInterface, EntityAdjustableInterface, EntityChangedInterface {

  /**
   * Clears the shipment's rate, its shipping service & method.
   *
   * @return $this
   */
  public function clearRate(): static;

  /**
   * Populates the shipment from the given proposed shipment.
   *
   * @param \Drupal\commerce_shipping\ProposedShipment $proposed_shipment
   *   The proposed shipment.
   */
  public function populateFromProposedShipment(ProposedShipment $proposed_shipment): void;

  /**
   * Gets the parent order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *   The order, or NULL if unknown.
   */
  public function getOrder(): ?OrderInterface;

  /**
   * Gets the parent order ID.
   *
   * @return int|null
   *   The order ID, or NULL if unknown.
   */
  public function getOrderId(): ?int;

  /**
   * Gets the package type.
   *
   * @return \Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface|null
   *   The shipment package type, or NULL if unknown.
   */
  public function getPackageType(): ?PackageTypePluginInterface;

  /**
   * Sets the package type.
   *
   * @param \Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface $package_type
   *   The package type.
   *
   * @return $this
   */
  public function setPackageType(PackageTypePluginInterface $package_type): static;

  /**
   * Gets the shipping method.
   *
   * @return \Drupal\commerce_shipping\Entity\ShippingMethodInterface|null
   *   The shipping method, or NULL if unknown.
   */
  public function getShippingMethod(): ?ShippingMethodInterface;

  /**
   * Sets the shipping method.
   *
   * @param \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method
   *   The shipping method.
   *
   * @return $this
   */
  public function setShippingMethod(ShippingMethodInterface $shipping_method): static;

  /**
   * Gets the shipping method ID.
   *
   * @return int|null
   *   The shipping method ID, or NULL if unknown.
   */
  public function getShippingMethodId(): ?int;

  /**
   * Sets the shipping method ID.
   *
   * @param int $shipping_method_id
   *   The shipping method ID.
   *
   * @return $this
   */
  public function setShippingMethodId(int $shipping_method_id): static;

  /**
   * Gets the shipping service.
   *
   * @return string|null
   *   The shipping service, or NULL if unknown.
   */
  public function getShippingService(): ?string;

  /**
   * Sets the shipping service.
   *
   * @param string $shipping_service
   *   The shipping service.
   *
   * @return $this
   */
  public function setShippingService(string $shipping_service): static;

  /**
   * Gets the service label.
   *
   * @return string|null
   *   The service label, or NULL if unknown.
   */
  public function getShippingServiceLabel(): ?string;

  /**
   * Sets the service label.
   *
   * @param string $service_label
   *   The service label.
   *
   * @return $this
   */
  public function setShippingServiceLabel(string $service_label): static;

  /**
   * Gets the shipping profile.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The shipping profile, NULL if not set.
   */
  public function getShippingProfile(): ?ProfileInterface;

  /**
   * Sets the shipping profile.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The shipping profile.
   *
   * @return $this
   */
  public function setShippingProfile(ProfileInterface $profile): static;

  /**
   * Gets the shipment title.
   *
   * @return string|null
   *   The shipment title, NULL if not set.
   */
  public function getTitle(): ?string;

  /**
   * Sets the shipment title.
   *
   * @param string $title
   *   The shipment title.
   *
   * @return $this
   */
  public function setTitle($title): static;

  /**
   * Gets the shipment items.
   *
   * @return \Drupal\commerce_shipping\ShipmentItem[]
   *   The shipment items.
   */
  public function getItems(): array;

  /**
   * Sets the shipment items.
   *
   * @param \Drupal\commerce_shipping\ShipmentItem[] $shipment_items
   *   The shipment items.
   *
   * @return $this
   */
  public function setItems(array $shipment_items): static;

  /**
   * Gets whether the shipment has items.
   *
   * @return bool
   *   TRUE if the shipment has items, FALSE otherwise.
   */
  public function hasItems(): bool;

  /**
   * Adds a shipment item.
   *
   * @param \Drupal\commerce_shipping\ShipmentItem $shipment_item
   *   The shipment item.
   *
   * @return $this
   */
  public function addItem(ShipmentItem $shipment_item): static;

  /**
   * Removes a shipment item.
   *
   * @param \Drupal\commerce_shipping\ShipmentItem $shipment_item
   *   The shipment item.
   *
   * @return $this
   */
  public function removeItem(ShipmentItem $shipment_item): static;

  /**
   * Gets the total quantity.
   *
   * Represents the sum of the quantities of all shipment items.
   *
   * @return string
   *   The total quantity.
   */
  public function getTotalQuantity(): string;

  /**
   * Gets the total declared value.
   *
   * Represents the sum of the declared values of all shipment items.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The total declared value, NULL if could not be determined.
   */
  public function getTotalDeclaredValue(): ?Price;

  /**
   * Gets the shipment weight.
   *
   * Calculated by adding the weight of each item to the
   * weight of the package type.
   *
   * @return \Drupal\physical\Weight|null
   *   The shipment weight, or NULL if unknown.
   */
  public function getWeight(): ?Weight;

  /**
   * Sets the shipment weight.
   *
   * @param \Drupal\physical\Weight $weight
   *   The shipment weight.
   *
   * @return $this
   */
  public function setWeight(Weight $weight): static;

  /**
   * Gets the original amount.
   *
   * This is the amount before promotions and fees are applied.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The original amount, or NULL if unknown.
   */
  public function getOriginalAmount(): ?Price;

  /**
   * Sets the original amount.
   *
   * @param \Drupal\commerce_price\Price $original_amount
   *   The original amount.
   *
   * @return $this
   */
  public function setOriginalAmount(Price $original_amount): static;

  /**
   * Gets the amount.
   *
   * Calculated from the original amount by applying
   * promotions and fees during order refresh.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The shipment amount, or NULL if unknown.
   */
  public function getAmount(): ?Price;

  /**
   * Sets the amount.
   *
   * @param \Drupal\commerce_price\Price $amount
   *   The shipment amount.
   *
   * @return $this
   */
  public function setAmount(Price $amount): static;

  /**
   * Gets the adjusted amount.
   *
   * @param string[] $adjustment_types
   *   The adjustment types to include in the adjusted price.
   *   Examples: fee, promotion, tax. Defaults to all adjustment types.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The adjusted amount, or NULL.
   */
  public function getAdjustedAmount(array $adjustment_types = []): ?Price;

  /**
   * Removes all adjustments that belong to the shipment.
   *
   * @return $this
   */
  public function clearAdjustments(): static;

  /**
   * Gets the shipment tracking code.
   *
   * Only available if shipping method supports tracking and the shipment
   * itself has been shipped.
   *
   * @return string|null
   *   The shipment tracking code, or NULL if unknown.
   */
  public function getTrackingCode(): ?string;

  /**
   * Sets the shipment tracking code.
   *
   * @param string $tracking_code
   *   The shipment tracking code.
   *
   * @return $this
   */
  public function setTrackingCode(string $tracking_code): static;

  /**
   * Gets the shipment state.
   *
   * @return \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface
   *   The shipment state.
   */
  public function getState(): StateItemInterface;

  /**
   * Gets a shipment data value with the given key.
   *
   * Used to store temporary data.
   *
   * @param string $key
   *   The key.
   * @param mixed|null $default
   *   The default value.
   *
   * @return mixed
   *   The shipment data.
   */
  public function getData($key, mixed $default = NULL): mixed;

  /**
   * Sets a shipment data value with the given key.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   */
  public function setData(string $key, mixed $value): static;

  /**
   * Unsets an shipment data value with the given key.
   *
   * @param string $key
   *   The key.
   *
   * @return $this
   */
  public function unsetData(string $key): static;

  /**
   * Gets the shipment creation timestamp.
   *
   * @return int|null
   *   The shipment creation timestamp.
   */
  public function getCreatedTime(): ?int;

  /**
   * Sets the shipment creation timestamp.
   *
   * @param int $timestamp
   *   The shipment creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime(int $timestamp): static;

  /**
   * Gets the shipment shipped timestamp.
   *
   * @return int|null
   *   The shipment shipped timestamp.
   */
  public function getShippedTime(): ?int;

  /**
   * Sets the shipment shipped timestamp.
   *
   * @param int $timestamp
   *   The shipment shipped timestamp.
   *
   * @return $this
   */
  public function setShippedTime(int $timestamp): static;

}
