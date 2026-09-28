<?php
/**
 *
 * This a copy of Drupal\redirect\EventSubscriber\RedirectRequestSubscriber
 * with the changes needed for the 410 functionallity
 *
 */

namespace Drupal\redirect_or_410\EventSubscriber;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\redirect\Exception\RedirectLoopException;
use Drupal\redirect\RedirectChecker;
use Drupal\redirect\RedirectRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Redirect subscriber for controller requests.
 */
class RedirectRequestSubscriber implements EventSubscriberInterface {

  /**
   * @var  \Drupal\redirect\RedirectRepository
   */
  protected $redirectRepository;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $config_410;

  /**
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\redirect\RedirectChecker
   */
  protected $checker;

  /**
   * @var \Symfony\Component\Routing\RequestContext
   */
  protected $context;

  /**
   * A path processor manager for resolving the system path.
   *
   * @var \Drupal\Core\PathProcessor\InboundPathProcessorInterface
   */
  protected $pathProcessor;

  /**
   * The HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * A router implementation which does not check access.
   *
   * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface
   */
  protected $accessUnawareRouter;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;
  /**
   * @param \Drupal\redirect\RedirectRepository $redirect_repository
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\redirect\RedirectChecker $checker
   * @param \Symfony\Component\Routing\RequestContext $context
   * @param \Drupal\Core\PathProcessor\InboundPathProcessorInterface $path_processor
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   * @param \Symfony\Component\Routing\Matcher\UrlMatcherInterface $access_unaware_router
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   */

  public function __construct(RedirectRepository $redirect_repository, LanguageManagerInterface $language_manager, ConfigFactoryInterface $config, AliasManagerInterface $alias_manager, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, RedirectChecker $checker, RequestContext $context, InboundPathProcessorInterface $path_processor, HttpKernelInterface $http_kernel, UrlMatcherInterface $access_unaware_router, RedirectDestinationInterface $redirect_destination, AccessManagerInterface $access_manager) {
    $this->redirectRepository = $redirect_repository;
    $this->languageManager = $language_manager;
    $this->config = $config->get('redirect.settings');
    $this->config_410 = $config->get('redirect_or_410.settings');
    $this->configFactory = $config;
    $this->aliasManager = $alias_manager;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->checker = $checker;
    $this->context = $context;
    $this->pathProcessor = $path_processor;
    $this->httpKernel = $http_kernel;
    $this->accessUnawareRouter = $access_unaware_router;
    $this->redirectDestination = $redirect_destination;
    $this->accessManager = $access_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // This needs to run before RouterListener::onKernelRequest(), which has
    // a priority of 32. Otherwise, that aborts the request if no matching
    // route is found.
    $events[KernelEvents::REQUEST][] = ['onKernelRequestCheckRedirect', 33];
    return $events;
  }

  /**
   * Handles the redirect if any found.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function onKernelRequestCheckRedirect(RequestEvent $event) {
    // Get a clone of the request. During inbound processing the request
    // can be altered. Allowing this here can lead to unexpected behavior.
    // For example, the path_processor.files inbound processor provided by
    // the system module alters both the path and the request; only the
    // changes to the request will be propagated, while the change to the
    // path will be lost.
    $request = clone $event->getRequest();

    if (!$this->checker->canRedirect($request)) {
      return;
    }

    // Get URL info and process it to be used for hash generation.
    $request_query = $request->query->all();

    if (str_starts_with($request->getPathInfo(), '/system/files/') && !$request->query->has('file')) {
      // Private files paths are split by the inbound path processor and the
      // relative file path is moved to the 'file' query string parameter. This
      // is because the route system does not allow an arbitrary amount of
      // parameters. We preserve the path as is returned by the request object.
      // @see \Drupal\system\PathProcessor\PathProcessorFiles::processInbound()
      $path = $request->getPathInfo();
    }
    else {
      // Do the inbound processing so that for example language prefixes are
      // removed.
      $path = $this->pathProcessor->processInbound($request->getPathInfo(), $request);
    }
    $path = trim($path, '/');

    $this->context->fromRequest($request);

    $cacheable_metadata = new CacheableMetadata();

    try {
      $redirect = $this->redirectRepository->findMatchingRedirect($path, $request_query, $this->languageManager->getCurrentLanguage()
        ->getId(), $cacheable_metadata);
    }
    catch (RedirectLoopException $e) {
      \Drupal::logger('redirect')
        ->warning('Redirect loop identified at %path for redirect %rid', [
          '%path' => $e->getPath(),
          '%rid' => $e->getRedirectId(),
        ]);
      $response = new Response();
      $response->setStatusCode(503);
      $response->setContent('Service unavailable');
      $event->setResponse($response);
      return;
    }

    if (NULL !== $redirect) {
      $headers = [
        'X-Redirect-ID' => $redirect->id(),
      ];

      $statusCode = (int) $redirect->getStatusCode();
      if ($statusCode === 410) {
        $response = $this->get410Response($request, $headers, $cacheable_metadata);
      }
      else {
        // Default redirect module response.
        $url = $redirect->getRedirectUrl();
        if ($this->config->get('passthrough_querystring')) {
          $url->setOption('query', (array) $url->getOption('query') + $request_query);
        }
        $response = new TrustedRedirectResponse($url->toString(), $redirect->getStatusCode(), $headers);
        $response->addCacheableDependency($cacheable_metadata);
      }
      // Invoke hook_redirect_response_alter().
      $this->moduleHandler->alter('redirect_response', $response, $redirect);
      $event->setResponse($response);
    }
  }

  /**
   *  Generates a 410 Gone HTTP response based on configuration and request data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param array $headers
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheable_metadata
   *
   * @return \Symfony\Component\HttpFoundation\Response
   * @throws \Exception
   */
  protected function get410Response(Request $request, array $headers, CacheableMetadata $cacheable_metadata): Response {
    $statusCode = 410;
    if ($this->config_410->get('fast_410')) {
      $content = $this->config_410->get('fast_410_message');
      $response = new CacheableResponse($content, $statusCode, $headers);
      $response->addCacheableDependency($cacheable_metadata);
    }
    else {
      // Send it to the configured 404 page with status code 410, falling back to
      // Drupal's default 404 route if the configured page is empty, invalid, or
      // inaccessible.
      $not_found_path = $this->getAccessibleNotFoundPath();

      $request_context = clone $this->accessUnawareRouter->getContext();
      $request_context->setMethod('GET');
      $this->accessUnawareRouter->setContext($request_context);

      $request->attributes->add($this->accessUnawareRouter->match($not_found_path));
      $request->query->add($this->redirectDestination->getAsArray() + ['_exception_statuscode' => $statusCode]);

      $response = $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST);

      if ($response->isSuccessful()) {
        $response->setStatusCode($statusCode);
      }

      $response->headers->add($headers);

      if ($response instanceof CacheableResponseInterface) {
        $response->addCacheableDependency($cacheable_metadata);
      }
    }
    return $response;
  }

  protected function getAccessibleNotFoundPath(): string {
    $custom_path = $this->config_410->get('fast_410_page');
    if (empty($custom_path)) {
      $custom_path = $this->configFactory->get('system.site')->get('page.404');
    }
    if (empty($custom_path)) {
      return '/system/404';
    }

    $url = Url::fromUserInput($custom_path);

    if ($url->isRouted()) {
      $access_result = $this->accessManager->checkNamedRoute($url->getRouteName(), $url->getRouteParameters(), NULL, TRUE);

      if (!$access_result->isAllowed()) {
        return '/system/404';
      }
    }

    // Make sure the path can actually be matched before using it.
    $this->accessUnawareRouter->match($custom_path);

    return $custom_path;
  }

}
