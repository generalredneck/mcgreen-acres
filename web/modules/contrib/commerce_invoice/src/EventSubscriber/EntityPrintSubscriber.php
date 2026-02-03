<?php

namespace Drupal\commerce_invoice\EventSubscriber;

use Drupal\entity_print\Event\PrintCssAlterEvent;
use Drupal\entity_print\Event\PrintEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add our CSS for the invoice PDFS.
 */
class EntityPrintSubscriber implements EventSubscriberInterface {

  /**
   * Alter the CSS renderable array and add our CSS.
   *
   * @param \Drupal\entity_print\Event\PrintCssAlterEvent $event
   *   The event object.
   */
  public function alterCss(PrintCssAlterEvent $event) {
    $event->getBuild()['#attached']['library'][] = 'commerce_invoice/entity-print-styling';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PrintEvents::CSS_ALTER => 'alterCss',
    ];
  }

}
