<?php

namespace Drupal\juicer_capture\Plugin\Block;

use Drupal\Core\Url;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

 /**
 * @Block(
 *   id = "juicer_capture_block",
 *   admin_label = @Translation("Juicer Capture Block")
 * )
 */
class JuicerCaptureBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $cache;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $cache) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cache = $cache;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cache.juicer_capture')
    );
  }

  public function build()
  {
    $cached = $this->cache->get('juicer_capture.cached_feed');
    if ($cached && !empty($cached->data)) {
      return [
        '#type' => 'markup',
        '#markup' => '<div id="juicer-capture-block-wrapper">' . $cached->data . '</div>',
        '#cache' => [
          'max-age' => 86400,        // Page & block can be cached for 1 day
          'tags' => ['juicer_capture'],
        ],
      ];
    }

    $markup = <<<HTML
<script src="https://assets.juicer.io/embed.js" type="text/javascript"></script>
<link href="https://assets.juicer.io/embed.css" media="all" rel="stylesheet" type="text/css" />
<ul class="juicer-feed" data-feed-id="mcgreenacres" data-per="6" data-pages="1">
  <h1 class="referral"><a href="https://www.juicer.io">Powered by Juicer.io</a></h1>
</ul>
HTML;
    return [
      '#type' => 'markup',
      '#markup' => '<div id="juicer-capture-block-wrapper">' . $markup . '</div>',
      '#cache' => [
        'max-age' => 0,              // Capturing mode → dynamic → no caching
      ],
      '#attached' => [
        'library' => ['juicer_capture/juicer_capture'],
        'drupalSettings' => [
          'juicerCapture' => [
            'capture' => TRUE,
            'endpoint' => Url::fromRoute('juicer_capture.store')->toString(),
            'selector' => '#juicer-capture-block-wrapper',
            'token' => $token
          ],
        ],
      ],
    ];
  }

}
