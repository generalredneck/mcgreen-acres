<?php

namespace Drupal\default_content;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A service for handling deletion of default content.
 *
 * @todo throw useful exceptions
 */
class Deleter implements DeleterInterface {


  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The content file storage.
   *
   * @var \Drupal\default_content\ContentFileStorageInterface
   */
  protected $contentFileStorage;

  /**
   * Constructs the default content manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\default_content\ContentFileStorageInterface $content_file_storage
   *   The file scanner.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, ContentFileStorageInterface $content_file_storage) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->contentFileStorage = $content_file_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteModuleContent($module) {

    $folder = \Drupal::service('extension.list.module')->getPath($module) . "/content";
    if (!file_exists($folder)) {
      return;
    }
    $entity_types = $this->calculateEntityTypes($folder);
    $uuids = $this->extractUuidsFromDir($folder, $entity_types);
    $message = [];
    foreach ($entity_types as $entity_type) {
      $entities = \Drupal::entityTypeManager()->getStorage($entity_type)->loadByProperties(['uuid' => $uuids]);

      foreach ($entities as $entity) {
        $entity->delete();
        $type = $entity->getEntityTypeId();
        if (!isset($message[$type])) {
          $message[$type] = 0;
        }
        $message[$type]++;
      }
      \Drupal::entityTypeManager()->getStorage($entity_type)->resetCache();
    }

    foreach ($message as $type => $count) {
      \Drupal::logger('default_content')->notice(\Drupal::translation()->formatPlural(
        $count,
        'Deleted one @type',
        'Deleted @count @types',
        ['@count' => $count, '@type' => $type]
      ));
    }
  }

  /**
   * Calculate entity types from default_content folder.
   */
  protected function calculateEntityTypes($folder) {
    $entity_types = array_keys($this->entityTypeManager->getDefinitions());
    foreach ($entity_types as $entity_type_id) {
      // Check if entity type contains field uuid.
      if (!($this->entityTypeManager->getDefinition($entity_type_id)
          ->entityClassImplements(ContentEntityInterface::class) &&
        $this->entityTypeManager->getDefinition($entity_type_id)
          ->hasKey('uuid')
      )
      ) {
        // Remove type from $entity_types.
        $index = array_search($entity_type_id, $entity_types);
        unset($entity_types[$index]);
      }
    }

    return $entity_types;
  }

  /**
   * Extract uuids from default content directory.
   */
  protected function extractUuidsFromDir($folder, $entity_types) {
    $pattern = '/uuid: (\w.*-\w.*-\w.*-\w.*-\w.*)/m';

    $uuids = [];
    /** @var \Drupal\Core\Entity\EntityInterface $entity_type */
    foreach ($entity_types as $entity_type_id) {
      if (!(file_exists($folder . '/' . $entity_type_id))) {
        continue;
      }

      $files = $this->contentFileStorage->scan($folder . '/' . $entity_type_id);

      foreach ($files as $file) {
        $contents = file_get_contents($file->uri);

        if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER, 0)) {
          foreach ($matches as $match) {
            if (!in_array($match[1], $uuids)) {
              $uuids[] = $match[1];
            }
          }
        }
      }
    }
    return $uuids;
  }

}
