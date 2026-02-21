<?php

namespace Drupal\jquery_downgrade\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * OOP Hook implementation for jQuery Downgrade.
 */
class JQueryDowngradeHooks {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Constructs the hook handler.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ThemeManagerInterface $theme_manager) {
    $this->configFactory = $config_factory;
    $this->themeManager = $theme_manager;
  }

  /**
   * Implements hook_page_attachments_alter().
   */
  #[Hook('page_attachments_alter')]
  public function alterAttachments(array &$attachments) {
    $config = $this->configFactory->get('jquery_downgrade.settings');
    $route_match = \Drupal::routeMatch();

    // Get configured settings.
    $node_ids = $config->get('node_ids') ?? [];
    $view_routes = $config->get('view_routes') ?? [];
    $enable_theme_downgrade = $config->get('enable_theme_downgrade') ?? FALSE;
    $downgrade_themes = $config->get('downgrade_themes') ?? [];

    // Check if we should downgrade jQuery.
    $downgrade_jquery = FALSE;

    // Check if current page is a node that should use jQuery 3.
    $node = $route_match->getParameter('node');
    $nid = $node instanceof NodeInterface ? $node->id() : (is_numeric($node) ? (int) $node : NULL);

    if ($nid && in_array($nid, $node_ids)) {
      $downgrade_jquery = TRUE;
    }

    // Check if the current route is a Views page needing jQuery 3.
    if (in_array($route_match->getRouteName(), $view_routes)) {
      $downgrade_jquery = TRUE;
    }

    // Check if theme-based downgrade is enabled and the active theme matches.
    if ($enable_theme_downgrade) {
      $active_theme = $this->themeManager->getActiveTheme()->getName();
      if (in_array($active_theme, $downgrade_themes)) {
        $downgrade_jquery = TRUE;
      }
    }

    // Apply jQuery downgrade if needed.
    if ($downgrade_jquery) {
      unset($attachments['#attached']['library']['core/jquery']);
      $attachments['#attached']['library'][] = 'jquery_downgrade/jquery_legacy';
    }
  }
}

