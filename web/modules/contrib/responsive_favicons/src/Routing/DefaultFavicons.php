<?php

namespace Drupal\responsive_favicons\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

/**
 * The DefaultFavicons route callback handler.
 *
 * @package Drupal\responsive_favicons\Routing
 * Listens to the dynamic route events.
 */
class DefaultFavicons implements ContainerInjectionInterface {

  public function __construct(protected ModuleHandlerInterface $moduleHandler) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $route_collection = new RouteCollection();

    // List of icons to redirect.
    // Note, in order for these to work alter the fast404 pattern to allow these
    // requests to hit Drupal. Please see the README for more information.
    $icons = [
      '/apple-touch-icon.png',
      '/apple-touch-icon-precomposed.png',
      '/browserconfig.xml',
      '/site.webmanifest',
      '/favicon.svg',
    ];
    // Try to avoid clashing with the favicon module.
    if (!$this->moduleHandler->moduleExists('favicon')) {
      $icons[] = '/favicon.ico';
    }
    foreach ($icons as $icon) {
      $route = new Route(
        // Path to attach this route to:
        $icon,
        // Route defaults:
        [
          '_controller' => '\Drupal\responsive_favicons\Controller\GetFile::deliver',
          '_title' => '',
        ],
        // Route requirements:
        [
          '_access' => 'TRUE',
        ]
      );

      // Prevent redirect from redirecting (normalizing) favicon routes.
      if ($this->moduleHandler->moduleExists('redirect')) {
        $route->setDefault('_disable_route_normalizer', TRUE);
      }

      // Add the route under a unique key.
      $key = preg_replace("/[^A-Za-z]/", '', $icon);
      $route_collection->add('responsive_favicons.' . $key, $route);
    }

    return $route_collection;
  }

}
