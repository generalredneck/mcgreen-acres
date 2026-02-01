<?php

namespace Drupal\redirect;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\Exception\RedirectLoopException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The redirect repository.
 */
class RedirectRepository {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $manager;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * An array of found redirect IDs to avoid recursion.
   *
   * @var array
   */
  protected $foundRedirects = [];

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The path validator.
   */
  protected PathValidatorInterface $pathValidator;

  /**
   * Constructs a \Drupal\redirect\EventSubscriber\RedirectRequestSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack|null $request_stack
   *   The request stack.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $module_handler
   *   The module handler.
   * @param \Drupal\Core\Path\PathValidatorInterface|null $path_validator
   *   The path validator.
   */
  public function __construct(EntityTypeManagerInterface $manager, Connection $connection, ConfigFactoryInterface $config_factory, ?RequestStack $request_stack = NULL, ?ModuleHandlerInterface $module_handler = NULL, ?PathValidatorInterface $path_validator = NULL) {
    $this->manager = $manager;
    $this->connection = $connection;
    $this->config = $config_factory->get('redirect.settings');
    if (!$request_stack) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $request_stack argument is deprecated in redirect:1.11.0 and it will be required in redirect:2.0.0. See https://www.drupal.org/project/redirect/issues/3451531', E_USER_DEPRECATED);
      // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
      $request_stack = \Drupal::requestStack();
    }
    $this->requestStack = $request_stack;
    if (!$module_handler) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $module_handler argument is deprecated in redirect:1.13.0 and it will be required in redirect:2.0.0. See https://www.drupal.org/project/redirect/issues/2879648', E_USER_DEPRECATED);
      // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
      $module_handler = \Drupal::moduleHandler();
    }
    $this->moduleHandler = $module_handler;
    if (!$path_validator) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $path_validator argument is deprecated in redirect:1.13.0 and it will be required in redirect:2.0.0. See https://www.drupal.org/project/redirect/issues/2879648', E_USER_DEPRECATED);
      // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
      $path_validator = \Drupal::service('path.validator');
    }
    $this->pathValidator = $path_validator;
  }

  /**
   * Finds a redirect from the given paths, query and language.
   *
   * @param array $source_paths
   *   An array of redirect source paths.
   * @param array $query
   *   The redirect source path query.
   * @param string $language
   *   The language for which is the redirect.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheable_metadata
   *   The cacheable metadata for all the redirects involved.
   *
   * @return \Drupal\redirect\Entity\Redirect|null
   *   The matched redirect entity or null if not found.
   *
   * @throws \Drupal\redirect\Exception\RedirectLoopException
   */
  public function findMatchingRedirectMultiple(array $source_paths, array $query = [], $language = Language::LANGCODE_NOT_SPECIFIED, ?CacheableMetadata $cacheable_metadata = NULL) {
    $hashes = [];
    foreach ($source_paths as $source_path) {
      $hashes = array_merge($hashes, $this->getHashesByPath($source_path, $query, $language));
    }

    return $this->findRedirectByHashes($hashes, reset($source_paths), $language, $cacheable_metadata);
  }

  /**
   * Gets a redirect for given path, query and language.
   *
   * @param string $source_path
   *   The redirect source path.
   * @param array $query
   *   The redirect source path query.
   * @param string $language
   *   The language for which is the redirect.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheable_metadata
   *   The cacheable metadata for all the redirects involved.
   *
   * @return \Drupal\redirect\Entity\Redirect|null
   *   The matched redirect entity or NULL if no redirect was found.
   *
   * @throws \Drupal\redirect\Exception\RedirectLoopException
   */
  public function findMatchingRedirect($source_path, array $query = [], $language = Language::LANGCODE_NOT_SPECIFIED, ?CacheableMetadata $cacheable_metadata = NULL) {
    $hashes = $this->getHashesByPath($source_path, $query, $language);
    return $this->findRedirectByHashes($hashes, $source_path, $language, $cacheable_metadata);
  }

  /**
   * Returns redirect hashes for the given source path.
   *
   * @param string $source_path
   *   The redirect source path.
   * @param array $query
   *   The redirect source path query.
   * @param string $language
   *   The language for which is the redirect.
   *
   * @return array
   *   An array of redirect hashes.
   */
  protected function getHashesByPath($source_path, array $query, $language) {
    $source_path = ltrim($source_path, '/');

    if ($this->moduleHandler->moduleExists('path')) {
      $route = $this->pathValidator->getUrlIfValid($source_path);
      $allow_alias = $this->config->get('allow_from_alias') ?? 0;
      $skip_alias = !$allow_alias;
      if ($route && $skip_alias) {
        // This URL path has a valid internal route and the Redirect settings
        // are not configured to allow redirects from aliases. Do not redirect.
        // See https://www.drupal.org/project/redirect/issues/2879648
        return [];
      }
    }

    $hashes = [Redirect::generateHash($source_path, $query, $language)];
    if ($language != Language::LANGCODE_NOT_SPECIFIED) {
      $hashes[] = Redirect::generateHash($source_path, $query, Language::LANGCODE_NOT_SPECIFIED);
    }

    // Add a hash without the query string if using passthrough query strings.
    if (!empty($query) && $this->config->get('passthrough_querystring')) {
      $hashes[] = Redirect::generateHash($source_path, [], $language);
      if ($language != Language::LANGCODE_NOT_SPECIFIED) {
        $hashes[] = Redirect::generateHash($source_path, [], Language::LANGCODE_NOT_SPECIFIED);
      }
    }

    return $hashes;
  }

  /**
   * Finds a redirect from the given hashes.
   *
   * @param array $hashes
   *   An array of redirect hashes.
   * @param string $source_path
   *   The redirect source path.
   * @param string $language
   *   The language for which is the redirect.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheable_metadata
   *   The cacheable metadata for all the redirects involved.
   *
   * @return \Drupal\redirect\Entity\Redirect|null
   *   The matched redirect entity or null if not found.
   */
  protected function findRedirectByHashes(array $hashes, $source_path, $language, ?CacheableMetadata $cacheable_metadata = NULL) {
    if (empty($hashes)) {
      return NULL;
    }
    // Load redirects by hash. A direct query is used to improve performance.
    $rid = $this->connection->query('SELECT rid FROM {redirect} WHERE hash IN (:hashes[]) AND enabled = 1 ORDER BY LENGTH(redirect_source__query) DESC', [':hashes[]' => $hashes])->fetchField();

    if (!empty($rid)) {
      // Check if this is a loop.
      if (in_array($rid, $this->foundRedirects)) {
        throw new RedirectLoopException('/' . $source_path, $rid);
      }
      $this->foundRedirects[] = $rid;

      $redirect = $this->load($rid);
      if ($cacheable_metadata) {
        $cacheable_metadata->addCacheableDependency($redirect);
      }

      // Find chained redirects.
      if ($recursive = $this->findByRedirect($redirect, $language, $cacheable_metadata)) {
        // Reset found redirects.
        $this->foundRedirects = [];
        return $recursive;
      }

      return $redirect;
    }

    // Reset found redirects.
    $this->foundRedirects = [];
    return NULL;
  }

  /**
   * Helper function to find recursive redirects.
   *
   * @param \Drupal\redirect\Entity\Redirect $redirect
   *   The redirect object.
   * @param string $language
   *   The language to use.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheable_metadata
   *   The cacheable metadata for all the redirects involved.
   */
  protected function findByRedirect(Redirect $redirect, $language, ?CacheableMetadata $cacheable_metadata = NULL) {
    $uri = $redirect->getRedirectUrl();
    $base_url = $this->requestStack->getCurrentRequest()->getBaseUrl();
    $generated_url = $uri->toString(TRUE);
    $path = ltrim(substr($generated_url->getGeneratedUrl(), strlen($base_url)), '/');
    $query = $uri->getOption('query') ?: [];
    $return_value = $this->findMatchingRedirect($path, $query, $language, $cacheable_metadata);
    return $return_value ? $return_value->addCacheableDependency($generated_url) : $return_value;
  }

  /**
   * Finds redirects based on the source path.
   *
   * @param string $source_path
   *   The redirect source path (without the query).
   *
   * @return \Drupal\redirect\Entity\Redirect[]
   *   Array of redirect entities.
   */
  public function findBySourcePath($source_path) {
    $ids = $this->manager->getStorage('redirect')->getQuery()
      ->accessCheck(TRUE)
      ->condition('redirect_source.path', $source_path, 'LIKE')
      ->condition('enabled', 1)
      ->execute();
    return $this->manager->getStorage('redirect')->loadMultiple($ids);
  }

  /**
   * Finds redirects based on the destination URI.
   *
   * @param string[] $destination_uri
   *   List of destination URIs, for example ['internal:/node/123'].
   *
   * @return \Drupal\redirect\Entity\Redirect[]
   *   Array of redirect entities.
   */
  public function findByDestinationUri(array $destination_uri) {
    $storage = $this->manager->getStorage('redirect');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('redirect_redirect.uri', $destination_uri, 'IN')
      ->condition('enabled', 1)
      ->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * Load redirect entity by id.
   *
   * @param int $redirect_id
   *   The redirect id.
   *
   * @return \Drupal\redirect\Entity\Redirect
   *   The redirect entity.
   */
  public function load($redirect_id) {
    return $this->manager->getStorage('redirect')->load($redirect_id);
  }

  /**
   * Loads multiple redirect entities.
   *
   * @param array $redirect_ids
   *   Redirect ids to load.
   *
   * @return \Drupal\redirect\Entity\Redirect[]
   *   List of redirect entities.
   */
  public function loadMultiple(?array $redirect_ids = NULL) {
    return $this->manager->getStorage('redirect')->loadMultiple($redirect_ids);
  }

}
