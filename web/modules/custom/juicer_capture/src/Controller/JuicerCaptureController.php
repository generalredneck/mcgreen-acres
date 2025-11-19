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

  public function store(Request $request) {
    $data = json_decode($request->getContent(), TRUE);

    if (empty($data['content'])) {
      return new JsonResponse(['error' => 'Missing content'], 400);
    }

    $this->cache->set(
      'juicer_capture.cached_feed',
      $data['content'],
      time() + 86400
    );

    return new JsonResponse(['status' => 'ok']);
  }

}
