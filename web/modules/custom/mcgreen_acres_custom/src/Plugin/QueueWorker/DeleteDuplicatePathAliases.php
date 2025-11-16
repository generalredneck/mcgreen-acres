<?php

// file: src/Plugin/QueueWorker/DeleteDuplicatePathAliases.php.
namespace Drupal\mcgreen_acres_custom\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deletes duplicate path aliases.
 *
 * @QueueWorker(
 *   id = "my_module_delete_duplicate_path_aliases",
 *   title = @Translation("Delete duplicate path aliases"),
 *   cron = {"time" = 30}
 * )
 */
class DeleteDuplicatePathAliases extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private LoggerChannelFactoryInterface $logger,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $alias = (string) $data;
    $query = $this->entityTypeManager->getStorage('path_alias')->getQuery();
    $entity_ids = $query
      ->accessCheck(FALSE)
      ->condition('alias', $alias)
      ->sort('id', 'DESC')
      ->execute();

    /** @var \Drupal\path_alias\Entity\PathAlias[] $entities */
    $entities = $this->entityTypeManager->getStorage('path_alias')->loadMultiple($entity_ids);

    $count = 0;
    // Keep the newest unique aliases by language and path, and delete the rest.
    $seen = [];
    foreach ($entities as $entity) {
      if (isset($seen[$entity->language()->getId()][$entity->getPath()])) {
        $entity->delete();
        $count++;
      }
      $seen[$entity->language()->getId()][$entity->getPath()] = TRUE;
    }
    $this->logger->get('my_module')->notice('Deleted ' . $count . ' duplicate path aliases for ' . $alias);
  }

}
