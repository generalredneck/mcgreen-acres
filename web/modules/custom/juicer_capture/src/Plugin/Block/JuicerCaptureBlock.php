<?php

namespace Drupal\juicer_capture\Plugin\Block;

use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Juicer Capture Block.
 *
 * @Block(
 *   id = "juicer_capture_block",
 *   admin_label = @Translation("Juicer Capture Block")
 * )
 */
class JuicerCaptureBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Juicer capture cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $cache) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cache.juicer_capture')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $cached = $this->cache->get('juicer_capture.cached_feed');
    $token = \Drupal::service('csrf_token')->get('juicer_capture_post');
    $session = \Drupal::request()->getSession();
    $session->set('juicer_capture_csrf_token', $token);
    if ($cached && !empty($cached->data)) {
      return [
        '#type' => 'markup',
        '#markup' => Markup::create('<div id="juicer-capture-block-wrapper">' . $cached->data . '</div>'),
        '#cache' => [
      // Page & block can be cached for 1 day.
          'max-age' => 86400,
          'tags' => ['juicer_capture'],
        ],
      ];
    }
    /*$markup = file_get_contents(__DIR__ . '/../../../McGreenAcres feed - Juicer Social-desktop.html');*/
    $markup = <<<HTML
<script type="text/javascript" src="https://www.juicer.io/embed/mcgreenacres/embed-code.js" async defer></script>
<ul class="juicer-feed" data-feed-id="mcgreenacres" data-per="7" data-pages="1">
  <h1 class="referral"><a href="https://www.juicer.io">Powered by Juicer.io</a></h1>
</ul>
HTML;
    return [
      '#type' => 'markup',
      '#markup' => Markup::create('<div id="juicer-capture-block-wrapper">' . $markup . '</div>'),
      '#cache' => [
    // Capturing mode → dynamic → no caching.
        'max-age' => 0,
      ],
      '#attached' => [
        'library' => ['juicer_capture/capture'],
        'drupalSettings' => [
          'juicerCapture' => [
            'capture' => TRUE,
            'endpoint' => Url::fromRoute('juicer_capture.store')->toString(),
            'selector' => '#juicer-capture-block-wrapper',
            'token' => $token,
          ],
        ],
      ],
    ];
  }

}
