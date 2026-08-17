<?php

namespace Drupal\simplenews_stats;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a simplenews stats entity type.
 */
interface SimplenewsStatsInterface extends ContentEntityInterface, EntityOwnerInterface {

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
   * @return \Drupal\simplenews_stats\SimplenewsStatsInterface
   *   The called simplenews stats entity.
   */
  public function setTitle(string $title): SimplenewsStatsInterface;

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
   * @return \Drupal\simplenews_stats\SimplenewsStatsInterface
   *   The called simplenews stats entity.
   */
  public function setCreatedTime(int $timestamp): SimplenewsStatsInterface;

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
   * @return \Drupal\simplenews_stats\SimplenewsStatsInterface
   *   The called simplenews stats entity.
   */
  public function setStatus(bool $status): SimplenewsStatsInterface;

  /**
   * Return the number of views.
   */
  public function getViews(): int;

  /**
   * Return the number of clicks.
   */
  public function getClicks(): int;

  /**
   * Return the number of emails sent.
   */
  public function getTotalMails(): int;

  /**
   * Return the Newsletter entity.
   */
  public function getNewsletterEntity(): EntityInterface;

  /**
   * Add one to views.
   *
   * @return $this
   */
  public function increaseView(): SimplenewsStatsInterface;

  /**
   * Add one to clicks.
   *
   * @return $this
   */
  public function increaseClick(): SimplenewsStatsInterface;

  /**
   * Add one to total Mails.
   *
   * @return $this
   */
  public function increaseTotalMail(): SimplenewsStatsInterface;

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
   * @return int|null
   *   The id of the associated newsletter entity.
   */
  public function getAssociatedEntityId(): ?int;

  /**
   * Returns the type of the associated newsletter entity.
   *
   * @return string|null
   *   The type of the associated newsletter entity.
   */
  public function getAssociatedEntityType(): ?string;

}
