<?php

namespace Drupal\simplenews_stats;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a simplenews stats entity type.
 */
interface SimplenewsStatsItemInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Gets the simplenews stats title.
   *
   * @return string|null
   *   Title of the simplenews stats.
   */
  public function getTitle(): ?string;

  /**
   * Sets the simplenews stats title.
   *
   * @param string $title
   *   The simplenews stats title.
   *
   * @return \Drupal\simplenews_stats\SimplenewsStatsItemInterface
   *   The called simplenews stats entity.
   */
  public function setTitle(string $title): SimplenewsStatsItemInterface;

  /**
   * Gets the simplenews stats creation timestamp.
   *
   * @return int
   *   Creation timestamp of the simplenews stats.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the simplenews stats creation timestamp.
   *
   * @param int $timestamp
   *   The simplenews stats creation timestamp.
   *
   * @return \Drupal\simplenews_stats\SimplenewsStatsItemInterface
   *   The called simplenews stats entity.
   */
  public function setCreatedTime(int $timestamp): SimplenewsStatsItemInterface;

  /**
   * Returns the simplenews stats status.
   *
   * @return bool
   *   TRUE if the simplenews stats is enabled, FALSE otherwise.
   */
  public function isEnabled(): bool;

  /**
   * Sets the simplenews stats status.
   *
   * @param bool $status
   *   TRUE to enable this simplenews stats, FALSE to disable.
   *
   * @return \Drupal\simplenews_stats\SimplenewsStatsItemInterface
   *   The called simplenews stats entity.
   */
  public function setStatus(bool $status): SimplenewsStatsItemInterface;

  /**
   * Returns the associated newsletter entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The associated newsletter entity.
   */
  public function getAssociatedEntity(): ?EntityInterface;

  /**
   * Returns the id of the associated newsletter entity.
   *
   * @return string|null
   *   The id of the associated newsletter entity.
   */
  public function getAssociatedEntityId(): ?string;

  /**
   * Returns the type of the associated newsletter entity.
   *
   * @return string|null
   *   The type of the associated newsletter entity.
   */
  public function getAssociatedEntityType(): ?string;

  /**
   * Returns the email that received this newsletter.
   *
   * @return string|null
   *   The email that received this newsletter.
   */
  public function getEmail(): ?string;

  /**
   * Returns the path that was clicked.
   *
   * @return string|null
   *   The path that was clicked.
   */
  public function getPath(): ?string;

}
