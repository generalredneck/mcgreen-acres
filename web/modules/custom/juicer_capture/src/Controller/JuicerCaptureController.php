<?php

namespace Drupal\juicer_capture\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class JuicerCaptureController extends ControllerBase {

  protected $cache;

  public function __construct(CacheBackendInterface $cache) {
    $this->cache = $cache;
  }

  public static function create($container) {
    return new static(
      $container->get('cache.juicer_capture')
    );
  }

  public function store(Request $request) {
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    $session = \Drupal::request()->getSession();
    $token = $session->get('juicer_capture_csrf_token');
    $session->remove('juicer_capture_csrf_token');
    if ($token !== $data['token']) {
      return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
    }

    if (empty($data['items'])) {
      return new JsonResponse(['error' => 'Missing content'], 400);
    }
    $row[0] = "<div class='column-1 col-md-4 col-lg-4'>";
    $row[1] = "<div class='column-2 col-md-4 col-lg-4'>";
    $row[2] = "<div class='column-3 col-md-4 col-lg-4'>";
    foreach ($data['items'] as $key => $item) {
      $row[$key % 3] .= $item;
    }
    $row[0] .= "</div>";
    $row[1] .= "</div>";
    $row[2] .= "</div>";
    $html = "<!-- Juicer Capture Cached Feed " . date('Y-m-d H:i:s') . " -->";
    $html .= <<<'HTML'
      <script src="https://assets.juicer.io/embed.js" type="text/javascript"></script>
      <link href="https://assets.juicer.io/embed.css" media="all" rel="stylesheet" type="text/css" />
      <ul class="juicer-feed j-initialized j-modern j-desktop modern loaded" data-feed-id="mcgreenacres" data-origin="iframe" data-overlay="false">
        <h1 class="referral "><a href="https://www.juicer.io/?utm_source=JuicerFreeFeed&amp;utm_medium=feed_ads&amp;utm_campaign=PoweredByTopLink&amp;utm_term=https://www.juicer.io/api/feeds/mcgreenacres/iframe&amp;referrer=www.juicer.io&amp;utm_id=mcgreenacres">Powered by</a></h1>
        <div class="j-stacker-wrapper" style="margin-left: -10px; margin-right: -10px;">
          <div class="">
            <div class="row">
HTML;
    $html .= $row[0] . $row[1] . $row[2] . "</div></div></div></ul>";

    $this->cache->set(
      'juicer_capture.cached_feed',
      $html,
      time() + 86400
    );

    return new JsonResponse(['status' => 'ok']);
  }

}
