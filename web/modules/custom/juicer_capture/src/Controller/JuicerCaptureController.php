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

    if (empty($data['html'])) {
      return new JsonResponse(['error' => 'Missing content'], 400);
    }

    $this->cache->set(
      'juicer_capture.cached_feed',
      $data['html'],
      time() + 86400
    );

    return new JsonResponse(['status' => 'ok']);
  }

}
