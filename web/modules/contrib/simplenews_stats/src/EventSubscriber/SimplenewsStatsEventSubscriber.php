<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats\EventSubscriber;

use Drupal\simplenews_stats\SimplenewsStatsEngine;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Simplenews stats EventSubscriber.
 */
class SimplenewsStatsEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected SimplenewsStatsEngine $simplenewsStatsEngine,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['simplenewsLog', 30];
    return $events;
  }

  /**
   * Catch and log new newsletter hit.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function simplenewsLog(RequestEvent $event): void {
    $value = $event->getRequest()->query->get('sstc');
    if (!$value) {
      return;
    }

    $this->simplenewsStatsEngine->addStatTags($value);
  }

}
