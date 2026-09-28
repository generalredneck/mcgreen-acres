<?php

namespace Drupal\simplenews;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Builds a listing of subscriber history records.
 *
 * This is the fallback listing for the collection route; sites that enable the
 * bundled "simplenews_subscriber_history" view override the page with it.
 *
 * @see \Drupal\simplenews\Entity\SubscriberHistory
 */
class SubscriberHistoryListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  protected $limit = 50;

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort('timestamp', 'DESC')
      ->sort('id', 'DESC');

    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return [
      'timestamp' => $this->t('When'),
      'mail' => $this->t('Email'),
      'source' => $this->t('Source'),
      'subscriptions' => $this->t('Subscribed after change'),
      'uid' => $this->t('By'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\simplenews\SubscriberHistoryInterface $entity */
    $newsletters = [];
    foreach ($entity->get('subscriptions') as $item) {
      if ($item->entity) {
        $newsletters[] = $item->entity->label();
      }
    }

    $author = $entity->getAuthor();
    $row = [
      'timestamp' => \Drupal::service('date.formatter')->format($entity->getTimestamp(), 'short'),
      'mail' => $entity->getMail(),
      'source' => $entity->getSource(),
      'subscriptions' => $newsletters ? implode(', ', $newsletters) : $this->t('None'),
      'uid' => ($author && !$author->isAnonymous()) ? $author->toLink() : $this->t('Anonymous'),
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    // History records are immutable.
    return [];
  }

}
