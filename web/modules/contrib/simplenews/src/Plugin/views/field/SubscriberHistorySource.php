<?php

namespace Drupal\simplenews\Plugin\views\field;

use Drupal\simplenews\SubscriberHistoryInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to display the human-readable source of a history record.
 *
 * The stored value is a machine token such as "route:entity.node.canonical";
 * \Drupal\simplenews\Entity\SubscriberHistory::getSource() turns it into
 * something readable ("Programmatic", or the route's title / name).
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("simplenews_subscriber_history_source")
 */
class SubscriberHistorySource extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);
    if ($entity instanceof SubscriberHistoryInterface) {
      return $entity->getSource();
    }
    // Fall back to the raw stored token.
    return $this->sanitizeValue($this->getValue($values));
  }

}
