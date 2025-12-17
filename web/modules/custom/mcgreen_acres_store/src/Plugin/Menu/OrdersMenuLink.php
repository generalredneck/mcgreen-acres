<?php

declare(strict_types=1);

namespace Drupal\mcgreen_acres_store\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @todo Provide description for this class.
 *
 * @DCG
 * Typically a module-defined menu link relies on
 * \Drupal\Core\Menu\MenuLinkDefault class that builds the link using plugin
 * definitions located in YAML files (MODULE_NAME.links.menu.yml). The purpose
 * of having custom menu link class is to make the link dynamic. Sometimes, the
 * title and the URL of a link should vary based on some context, i.e. user
 * being logged, current page URL, etc. Check out the parent classes for the
 * methods you can override to make the link dynamic.
 *
 * @DCG It is important to supply the link with correct cache metadata.
 * @see self::getCacheContexts()
 * @see self::getCacheTags()
 *
 * @DCG
 * You can apply the class to a link as follows.
 * @code
 * foo.example:
 *   title: Example
 *   route_name: foo.example
 *   menu_name: main
 *   class: \Drupal\foo\Plugin\Menu\FooMenuLink
 * @endcode
 */
final class OrdersMenuLink extends MenuLinkDefault {
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
    return Url::fromUri('internal:/user/' . $this->currentUser->id() . '/orders');
  }
}
