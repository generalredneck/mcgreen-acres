<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\simplenews_stats\SimplenewsStatsTools;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for simplenews stats autocomplete routes.
 *
 * @package Drupal\simplenews_stats\Controller
 */
class SimplenewsStatsAutocompleteController extends ControllerBase {

  public function __construct(
    protected Connection $database,
    protected EntityRepositoryInterface $entityRepository,
    protected SimplenewsStatsTools $simplenewsStatsTools,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity.repository'),
      $container->get('simplenews_stats.tools')
    );
  }

  /**
   * Route Callback: Autocomplete callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The autocomplete JSON response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function autocompleteEntityAssociated(Request $request): JsonResponse {
    // Get the searched string from the URL, if it exists.
    $string = $request->query->get('q');
    $content_bundles = simplenews_get_content_types();

    if (!$string || empty($content_bundles)) {
      return new JsonResponse([]);
    }

    /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
    $nodeStorage = $this->entityTypeManager()->getStorage('node');

    $query = $nodeStorage->getQuery();
    $query->condition('type', $content_bundles, 'IN')
      ->condition('title', "$string%", 'LIKE');

    $results = [];
    foreach ($query->accessCheck()->execute() as $id) {
      $entity = $nodeStorage->load($id);

      $results[] = [
        'value' => $this->simplenewsStatsTools->getEntityLabel($entity, TRUE),
        'label' => $this->simplenewsStatsTools->getEntityLabel($entity, TRUE),
      ];
    }

    return new JsonResponse($results);
  }

  /**
   * Route Callback: Autocomplete callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The autocomplete JSON response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function autocompleteUser(Request $request): JsonResponse {
    $string = $request->query->get('q');
    // Get the typed string from the URL, if it exists.
    if (!$string) {
      return new JsonResponse([$string]);
    }

    $userStorage = $this->entityTypeManager()->getStorage('user');
    $snsiMapping = $this->entityTypeManager()
      ->getStorage('simplenews_stats_item')
      ->getTableMapping();
    $userMapping = $userStorage->getTableMapping();

    $query = $this->database->select($snsiMapping->getBaseTable(), 'snsi');
    $query->fields('snsi', ['uid']);
    $query->leftJoin($userMapping->getDataTable(), 'user', 'user.uid=snsi.uid');
    $query->condition('user.name', "{$string}%", 'LIKE')
      ->distinct();

    $results = [];
    foreach ($query->execute() as $result) {
      $entity = $userStorage->load($result->uid);

      $results[] = [
        'value' => $this->simplenewsStatsTools->getEntityLabel($entity),
        'label' => $this->simplenewsStatsTools->getEntityLabel($entity),
      ];
    }
    return new JsonResponse($results);
  }

}
