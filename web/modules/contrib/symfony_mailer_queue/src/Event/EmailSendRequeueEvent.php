<?php

namespace Drupal\symfony_mailer_queue\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\symfony_mailer_queue\SymfonyMailerQueueItem;

/**
 * Defines an email send requeue event for the Symfony mailer queue.
 */
class EmailSendRequeueEvent extends Event {

  /**
   * Constructs an EmailSendRequeueEvent object.
   *
   * @param \Drupal\symfony_mailer_queue\SymfonyMailerQueueItem $item
   *   The Symfony mailer queue item.
   */
  public function __construct(public readonly SymfonyMailerQueueItem $item) {}

}
