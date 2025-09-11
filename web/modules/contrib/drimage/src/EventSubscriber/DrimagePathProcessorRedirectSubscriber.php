<?php

namespace Drupal\drimage\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PathProcessor\PathProcessorManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets caching headers for redirects caused byPathProcessorImageStyles.
 *
 * This is a workaround for the fact that the response is caused by an incoming
 * path processor, which does not have access to the response object.
 *
 * @see \Drupal\drimage\PathProcessor\PathProcessorImageStyles
 */
class DrimagePathProcessorRedirectSubscriber implements EventSubscriberInterface {

  /**
   * The path processor service.
   *
   * @var \Drupal\Core\PathProcessor\PathProcessorManager
   */
  protected PathProcessorManager $pathProcessorManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new DrimagePathProcessorRedirectSubscriber.
   *
   * @param \Drupal\Core\PathProcessor\PathProcessorManager $pathProcessorManager
   *   The path processor service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(
    PathProcessorManager $pathProcessorManager,
    ConfigFactoryInterface $configFactory
  ) {
    $this->pathProcessorManager = $pathProcessorManager;
    $this->configFactory = $configFactory;
  }

  /**
   * Sets caching headers for redirects caused by PathProcessorImageStyles.
   */
  public function disableCaching(ResponseEvent $event): void {
    $response = $event->getResponse();
    if (!$response instanceof RedirectResponse) {
      return;
    }

    // Process the request URI, mainly to filter out language prefixes.
    $requestUri = $this->pathProcessorManager->processInbound(
      $event->getRequest()->getRequestUri(),
      $event->getRequest()
    );

    if (substr($requestUri, 0, 8) !== '/drimage') {
      return;
    }

    $settingsConfig = $this->configFactory->get('drimage.settings');
    $maxage = $settingsConfig->get('cache_max_age');

    if ($maxage === 0) {
      $response->headers->set('Cache-Control', 'must-revalidate, no-cache, private');
    }
    elseif (is_int($maxage)) {
      $response->setMaxAge($maxage);
    }
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['disableCaching', 100];
    return $events;
  }

}
