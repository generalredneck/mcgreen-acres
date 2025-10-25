<?php

namespace Drupal\drimage\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\drimage\ImageStyleRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Delete all generated drimage image styles.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * The image style repository.
   *
   * @var \Drupal\drimage\ImageStyleRepositoryInterface
   */
  protected $imageStyleRepository;

  /**
   * Constructs a new ConfigSubscriber object.
   *
   * @param \Drupal\drimage\ImageStyleRepositoryInterface $imageStyleRepository
   *   The image style repository.
   */
  public function __construct(ImageStyleRepositoryInterface $imageStyleRepository) {
    $this->imageStyleRepository = $imageStyleRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE] = ['onConfigSave'];
    return $events;
  }

  /**
   * Delete all generated drimage image styles.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The config crud event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    if (in_array($event->getConfig()->getName(), ['drimage.settings', 'imageapi_optimize.settings'], TRUE)) {
      $this->imageStyleRepository->deleteAll();
    }
  }

}
