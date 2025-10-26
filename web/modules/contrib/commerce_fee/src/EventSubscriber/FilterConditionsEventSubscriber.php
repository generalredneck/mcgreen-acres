<?php

namespace Drupal\commerce_fee\EventSubscriber;

use Drupal\commerce\Event\FilterConditionsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FilterConditionsEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [
      'commerce.filter_conditions' => 'onFilterConditions',
    ];
    return $events;
  }

  /**
   * Removes unneeded conditions.
   *
   * Fees have store and order_types base fields that are used for filtering,
   * so there's no need to have conditions targeting the same data.
   *
   * @param \Drupal\commerce\Event\FilterConditionsEvent $event
   *   The event.
   */
  public function onFilterConditions(FilterConditionsEvent $event): void {
    if ($event->getParentEntityTypeId() == 'commerce_fee') {
      $definitions = $event->getDefinitions();
      unset($definitions['order_store']);
      unset($definitions['order_type']);
      $event->setDefinitions($definitions);
    }
  }

}
