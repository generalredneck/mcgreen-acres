<?php

namespace Drupal\responsive_favicons;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Responsive favicons service.
 */
class ResponsiveFavicons implements ContainerInjectionInterface {

  /**
   * Constructs a ResponsiveFavicons object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator service.
   * @param \Drupal\Core\Asset\AssetQueryStringInterface|\Drupal\Core\State\StateInterface $assetQueryString
   *   The asset query string.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected AssetQueryStringInterface $assetQueryString,
    protected CacheBackendInterface $cache,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('file_url_generator'),
      $container->get('asset.query_string'),
      $container->get('cache.data'),
      $container->get('module_handler'),
    );
  }

  /**
   * Load the responsive favicons that are valid.
   */
  public function loadAll() {
    $icon_tags = &drupal_static(__FUNCTION__);
    if (!isset($icon_tags)) {
      $config = $this->configFactory->get('responsive_favicons.settings');
      $cid = $this->getCacheId($config);
      if ($cached = $this->cache->get($cid)) {
        $icon_tags = $cached->data;
      }
      else {
        $tags = $config->get('tags');
        if (empty($tags)) {
          $icon_tags = [
            'links' => [],
            'metatags' => [],
            'missing' => [],
          ];
        }
        else {
          $html = implode(PHP_EOL, $tags);
          $icon_tags = $this->validateTags($html, $config);
          // Allow other modules to alter the loaded $icon_tags.
          $this->moduleHandler->alter('responsive_favicons_tags', $icon_tags);
          $cache_tags = $config->getCacheTags();
          $this->cache->set($cid, $icon_tags, $this->cache::CACHE_PERMANENT, $cache_tags);
        }
      }
    }

    return $icon_tags;
  }

  /**
   * Gets the icons' cache identifier.
   *
   * @return string
   *   The icons' cache identifier.
   */
  public function getCacheId($config) {
    $type = $config->get('path_type');
    $path = $config->get('path');
    return "responsive_favicons:$type:$path:icons";
  }

  /**
   * Validate the contributed links and meta tags.
   *
   * Helper function to check whether responsive favicon files exist and are
   * readable. This function also strips any pasted content that is not a link
   * or a meta tag.
   *
   * @param string $html
   *   The HTML tag.
   * @param mixed|null $config
   *   The module configuration.
   *
   * @return array
   *   The attributes of found and missing links and meta-tags.
   */
  public function validateTags($html, $config = NULL) {
    global $base_path;

    // Don't process DOM if HTML code is empty.
    if (empty($html)) {
      return [
        'links' => [],
        'metatags' => [],
        'missing' => [],
      ];
    }

    // Use default config if not provided as parameter.
    if (is_null($config)) {
      $config = $this->configFactory->get('responsive_favicons.settings');
    }

    $links = [];
    $metatags = [];
    $missing = [];

    $dom = new \DOMDocument();
    $dom->loadHTML($html);

    // DRUPAL_ROOT contains the subdirectory of the Drupal install (if present),
    // in our case we do not want this as $file_path already contains this.
    $docroot = preg_replace('/' . preg_quote($base_path, '/') . '$/', '/', DRUPAL_ROOT);

    // Find all the apple touch icons.
    $dom_links = $dom->getElementsByTagName('link');

    // Loop through link tags and store their attributes in associative array.
    $this->processDomLinks(
      $dom, $dom_links, $links, $missing, $docroot, $config);

    // Find any Windows 8 meta tags.
    $dom_metatags = $dom->getElementsByTagName('meta');

    // Loop through meta-tags and store their attributes in associative array.
    $this->processDomMetatags(
      $dom, $dom_metatags, $metatags, $missing, $docroot, $config);

    return [
      'links' => $links,
      'metatags' => $metatags,
      'missing' => $missing,
    ];
  }

  /**
   * Process favicon DOM links.
   *
   * @param DOMDocument $dom
   *   The DOM document.
   * @param DOMNodeList $dom_links
   *   The DOM links.
   * @param array $links
   *   The found links array.
   * @param array $missing
   *   The missing tags array.
   * @param string $docroot
   *   The Drupal file document root.
   * @param mixed $config
   *   The module configuration.
   */
  private function processDomLinks(
    \DOMDocument $dom,
    \DOMNodeList $dom_links,
    array &$links,
    array &$missing,
    string $docroot,
    mixed $config,
  ) {
    $show_missing = $config->get('show_missing') ?? 0;
    /** @var DOMElement $link */
    foreach ($dom_links as $link) {
      if ($link->hasAttributes()) {
        $attributes = [];
        foreach ($link->attributes as $attr) {
          $attributes[$attr->nodeName] = $attr->nodeValue;
        }
        $path = $this->normalisePath($attributes['href'], $config);
        $attributes['href'] = $path;
        if ($this->fileExists($path, $docroot)) {
          $links[] = $attributes;
        }
        else {
          if ($show_missing) {
            $links[] = $attributes;
          }
          $missing[] = $dom->saveHTML($link);
        }
      }
    }
  }

  /**
   * Process favicon DOM metatags.
   *
   * @param DOMDocument $dom
   *   The DOM document.
   * @param DOMNodeList $dom_metatags
   *   The DOM metatags.
   * @param array $metatags
   *   The found metatags array.
   * @param array $missing
   *   The missing tags array.
   * @param string $docroot
   *   The Drupal file document root.
   * @param mixed $config
   *   The module configuration.
   */
  private function processDomMetatags(
    \DOMDocument $dom,
    \DOMNodeList $dom_metatags,
    array &$metatags,
    array &$missing,
    string $docroot,
    mixed $config,
  ) {
    $show_missing = $config->get('show_missing') ?? 0;
    /** @var DOMElement $meta */
    foreach ($dom_metatags as $meta) {
      if ($meta->hasAttributes()) {
        $attributes = [];
        foreach ($meta->attributes as $attr) {
          $attributes[$attr->nodeName] = $attr->nodeValue;
        }
        // We only validate the image file.
        if ($attributes['name'] === 'msapplication-TileImage') {
          $path = $this->normalisePath($attributes['content'], $config);
          $attributes['content'] = $path;
          if ($this->fileExists($path, $docroot)) {
            $metatags[] = $attributes;
          }
          else {
            if ($show_missing) {
              $metatags[] = $attributes;
            }
            $missing[] = $dom->saveHTML($meta);
          }
        }
        // Add any other meta-tags and assume they contain no images.
        else {
          $metatags[] = $attributes;
        }
      }
    }
  }

  /**
   * Determine if an icon path is valid.
   *
   * @param string $path
   *   The icon path.
   * @param string $docroot
   *   The Drupal document root.
   *
   * @return bool
   *   TRUE is the URL is valid, FALSE otherwise.
   */
  private function fileExists(string $path, string $docroot): bool {
    if (UrlHelper::isExternal($path)) {
      // Check that the external resource is readable.
      $exists = fopen($path, 'r');
      return ($exists && @fread($exists, 1) !== FALSE);
    }
    else {
      // Remove any url parameters.
      $url_path = parse_url($path, PHP_URL_PATH);
      // Check that file exists.
      $file_path = $docroot . $url_path;
      return (file_exists($file_path) && is_readable($file_path));
    }
  }

  /**
   * Help to normalise the path to the icons.
   *
   * @param string $file_path
   *   The filename of the icon.
   * @param mixed|null $config
   *   The module configuration.
   *
   * @return string
   *   The full relative path to the icon within public files.
   */
  public function normalisePath($file_path, $config = NULL) {
    // Use default config if not provided as parameter.
    if (is_null($config)) {
      $config = $this->configFactory->get('responsive_favicons.settings');
    }

    // If local path.
    if (!UrlHelper::isExternal($file_path)) {
      // Convert backslashes when running on Windows.
      $file_path = str_replace('\\', '/', $file_path);

      if ($config->get('path_type') === 'upload') {
        $file_uri = 'public://' . $config->get('path') . $file_path;
        $file_path = $this->fileUrlGenerator->generateString($file_uri);
      }
      else {
        $file_path = $config->get('path') . $file_path;
      }
    }

    // Try to convert the file path from absolute to relative.
    if (UrlHelper::isExternal($file_path)) {
      $file_path = $this->fileUrlGenerator->transformRelative($file_path);
    }

    // Append browser cache refresh suffix.
    if ($config->get('cache_refresh_suffix')) {
      $query_string = $this->assetQueryString->get();
      if (!empty($query_string)) {
        $file_path .= (str_contains($file_path, '?') ? '&' : '?') . $query_string;
      }
    }

    // Allow other modules to alter the normalised icon $file_path.
    $this->moduleHandler->alter('responsive_favicons_icon_path', $file_path, $config);

    return $file_path;
  }

  /**
   * Check if a link with the specified attribute name and value exists.
   *
   * @param array $tags
   *   The array of "link" and "meta" tags.
   * @param string $attribute_name
   *   The link attribute name we are looking for.
   * @param string $attribute_value
   *   The link attribute value we are looking for.
   *
   * @return bool
   *   TRUE if a corresponding link has been found, FALSE otherwise.
   */
  public function hasLink(
    array $tags,
    string $attribute_name,
    string $attribute_value,
  ) {
    $links = $tags['links'];
    foreach ($links as $link_attributes) {
      foreach ($link_attributes as $name => $value) {
        if ($name === $attribute_name && $value === $attribute_value) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}
