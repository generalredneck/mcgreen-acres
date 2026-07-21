<?php

namespace Drupal\mcgreen_acres_newsletter_segments\Plugin\simplenews\RecipientHandler;

use Drupal\simplenews\Plugin\simplenews\RecipientHandler\RecipientHandlerSelectBase;
use Drupal\simplenews\SubscriberInterface;

/**
 * Sends an issue to subscribers filtered by taxonomy tag.
 *
 * Reads 'field_send_to_tags' and 'field_exclude_tags' off the issue node.
 * An empty send-to list matches all subscribers of the newsletter (same as
 * the default handler); the exclude list, if set, is applied on top.
 *
 * @RecipientHandler(
 *   id = "simplenews_tags",
 *   title = @Translation("Subscribers by tag")
 * )
 */
class RecipientHandlerTags extends RecipientHandlerSelectBase {

  /**
   * {@inheritdoc}
   */
  protected function buildRecipientQuery() {
    $select = $this->connection->select('simplenews_subscriber', 's');
    $select->innerJoin('simplenews_subscriber__subscriptions', 't', 's.id = t.entity_id');
    $select->addField('s', 'id', 'snid');
    $select->addField('t', 'subscriptions_target_id', 'newsletter_id');
    $select->condition('t.subscriptions_target_id', $this->getNewsletterId());
    $select->condition('s.status', SubscriberInterface::ACTIVE);

    $include_tags = $this->getTagIds('field_send_to_tags');
    if ($include_tags) {
      $select->innerJoin('simplenews_subscriber__field_tags', 'inc', 's.id = inc.entity_id');
      $select->condition('inc.field_tags_target_id', $include_tags, 'IN');
      $select->distinct();
    }

    $exclude_tags = $this->getTagIds('field_exclude_tags');
    if ($exclude_tags) {
      $excluded = $this->connection->select('simplenews_subscriber__field_tags', 'ex')
        ->fields('ex', ['entity_id'])
        ->condition('ex.field_tags_target_id', $exclude_tags, 'IN');
      $select->condition('s.id', $excluded, 'NOT IN');
    }

    return $select;
  }

  /**
   * Gets the taxonomy term IDs referenced by a field on the issue.
   *
   * @param string $field_name
   *   The entity reference field name on the issue node.
   *
   * @return int[]
   *   Term IDs, or an empty array if the field is empty or missing.
   */
  protected function getTagIds(string $field_name): array {
    if (!$this->issue->hasField($field_name)) {
      return [];
    }
    return array_column($this->issue->get($field_name)->getValue(), 'target_id');
  }

}
