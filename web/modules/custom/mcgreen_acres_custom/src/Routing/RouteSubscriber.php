<?php

namespace Drupal\mcgreen_acres_custom\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Define custom access for '/user/{user}'.
    if ($route = $collection->get('entity.user.canonical')) {
      $route->setRequirement('_custom_access', '\Drupal\mcgreen_acres_custom\Access\UserAccessCheck::access');
    }
  }

}
