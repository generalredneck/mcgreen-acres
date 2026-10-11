<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\HttpFoundation\Response;
use Drupal\simplenews_stats\SimplenewsStatsEngine;
use Drupal\simplenews_stats\SimplenewsStatsAllowedLinks;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;

/**
 * Provides route responses for hits and stats page.
 */
class SimplenewsStatsController extends ControllerBase {

  public function __construct(
    protected RequestStack $request,
    protected SimplenewsStatsEngine $simplenewsStatsEngine,
    protected SimplenewsStatsAllowedLinks $simplenewsStatsAllowedLinks,
    protected ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('simplenews_stats.engine'),
      $container->get('simplenews_stats.allowedlinks'),
      $container->get('extension.list.module')
    );
  }

  /**
   * Route callback: Send image to log view action.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The pixel image response.
   */
  public function hitView(): Response {
    $response = new Response();
    $image = file_get_contents($this->moduleExtensionList->getPath('simplenews_stats') . '/assets/image/simple.png');
    $response->setContent($image);
    $response->headers->set('Content-Type', 'image/png');
    $response->headers->set('Content-Transfer-Encoding', 'binary');
    return $response;
  }

  /**
   * Catch click and redirect to link.
   *
   * @param string $tag
   *   The tag.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirection response.
   */
  public function hitClick(string $tag): TrustedRedirectResponse|RedirectResponse {
    $entities = $this->simplenewsStatsEngine->getTagEntities($tag);
    if ($entities === FALSE) {
      return new RedirectResponse('/');
    }

    // Log click and redirect to the external link if it allowed.
    $link = $this->request->getCurrentRequest()->query->get('link');
    if ($this->simplenewsStatsAllowedLinks->isLinkExist($entities['entity'], $link)) {
      $this->simplenewsStatsEngine->addStatTags($tag, $link);

      // Use TrustedRedirectResponse for this external redirection.
      $url = Url::fromUri($link);
      $response = new TrustedRedirectResponse($url->toString());
      $response->addCacheableDependency($url);
      return $response;
    }

    // Redirect to the entity if the link is not allowed.
    return new RedirectResponse($entities['entity']->toUrl()->toString());
  }

}
