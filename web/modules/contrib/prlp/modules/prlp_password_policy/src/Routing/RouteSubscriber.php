<?php

namespace Drupal\prlp_password_policy\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters password reset route to use custom controller.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('user.reset.form')) {
      $route->setDefault(
        '_controller',
        '\Drupal\prlp_password_policy\Controller\PrlpPasswordPolicyController::prlpPasswordPolicyGetResetPassForm'
      );
    }
  }

}
