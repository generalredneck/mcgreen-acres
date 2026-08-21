<?php

namespace Drupal\responsive_favicons\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * The GetFile controller implementation.
 *
 * @package Drupal\responsive_favicons\Controller
 */
class GetFile extends ControllerBase {

  public function __construct(protected MimeTypeGuesserInterface $mimeTypeGuesser) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('file.mime_type.guesser'),
    );
  }

  /**
   * Creates a file object for the requested icon path.
   *
   * @param string $file_path
   *   The icon filename.
   *
   * @return object
   *   A file object.
   */
  private function getFile($file_path) {
    $config = $this->config('responsive_favicons.settings');
    if ($config->get('path_type') === 'upload') {
      $uri = 'public://' . $config->get('path') . $file_path;
    }
    else {
      $uri = DRUPAL_ROOT . $config->get('path') . $file_path;
    }

    $file = new \stdClass();
    $file->uri = $uri;
    $file->filemime = $this->mimeTypeGuesser->guessMimeType($uri);
    $file->filesize = @filesize($uri);

    return $file;
  }

  /**
   * Attempts to send the raw file back in the response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   a Request object.
   */
  public function deliver(Request $request) {
    // Get the file.
    $file = $this->getFile($request->getRequestUri());

    if (!is_object($file) || !is_file($file->uri) || !is_readable($file->uri)) {
      throw new NotFoundHttpException();
    }

    $headers = [
      'Content-Length' => $file->filesize,
      'Content-Type' => $file->filemime,
    ];

    $response = new BinaryFileResponse($file->uri, 200, $headers, TRUE, NULL, TRUE);

    // Retrieve the default max-age from the system performance configuration.
    $max_age = $this->config('system.performance')->get('cache.page.max_age');

    // If the browser page cache is enabled.
    if ($max_age > 0) {
      // Apply cache age headers to the response.
      $response->setMaxAge($max_age);
      $response->setSharedMaxAge($max_age);
    }

    $response->prepare($request);
    $response->send();
    exit;
  }

}
