<?php

declare(strict_types=1);

namespace Drupal\mcgreen_acres_store\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Account menu link to the current user's newsletter subscriptions.
 */
final class NewslettersMenuLink extends MenuLinkDefault {
  protected $currentUser;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticMenuLinkOverridesInterface $static_override, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);

    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('current_user')
    );
  }

  public function getUrlObject($title_attribute = TRUE) {
    return Url::fromUri('internal:/user/' . $this->currentUser->id() . '/simplenews');
  }

}
