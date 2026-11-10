<?php

namespace Drupal\simplenews;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the subscriber history entity type.
 */
class SubscriberHistoryViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Render "source" via SubscriberHistory::getSource() instead of showing
    // the raw "route:*" token.
    $data['simplenews_subscriber_history']['source']['field']['id'] = 'simplenews_subscriber_history_source';

    // Filter the subscribed-newsletter set by newsletter, like the subscriber
    // list does.
    $data['simplenews_subscriber_history__subscriptions']['subscriptions_target_id']['filter'] = [
      'id' => 'in_operator',
      'options callback' => 'simplenews_newsletter_list',
      'allow empty' => TRUE,
    ];

    // The per-subscriber history page passes the address as an argument.
    $data['simplenews_subscriber_history']['mail']['argument'] = [
      'id' => 'string',
    ];

    return $data;
  }

}
