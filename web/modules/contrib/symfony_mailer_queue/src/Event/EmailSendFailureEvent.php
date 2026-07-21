<?php

namespace Drupal\symfony_mailer_queue\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\symfony_mailer_queue\SymfonyMailerQueueItem;

/**
 * Defines an email send failure event for the Symfony mailer queue.
 */
class EmailSendFailureEvent extends Event {

  /**
   * Constructs an EmailSendFailureEvent object.
   *
   * @param \Drupal\symfony_mailer_queue\SymfonyMailerQueueItem $item
   *   The Symfony mailer queue item.
   */
  public function __construct(public readonly SymfonyMailerQueueItem $item) {}

}
