<?php

namespace Drupal\commerce_fee\EventSubscriber;

use Drupal\commerce\Event\CommerceEvents;
use Drupal\commerce\Event\ReferenceablePluginTypesEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registers fee types as referenceable.
 */
class ReferenceablePluginTypesSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CommerceEvents::REFERENCEABLE_PLUGIN_TYPES => 'onPluginTypes',
    ];
  }

  /**
   * Registers our plugin types as referenceable.
   *
   * @param \Drupal\commerce\Event\ReferenceablePluginTypesEvent $event
   *   The event.
   */
  public function onPluginTypes(ReferenceablePluginTypesEvent $event): void {
    $plugin_types = $event->getPluginTypes();
    $plugin_types['commerce_fee'] = $this->t('Fee');
    $event->setPluginTypes($plugin_types);
  }

}
