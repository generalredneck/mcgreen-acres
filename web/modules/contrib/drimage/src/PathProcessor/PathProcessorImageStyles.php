<?php

namespace Drupal\drimage\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a path processor to rewrite drimage request URLs.
 *
 * As the route system does not allow arbitrary amount of parameters convert
 * the file path to a query parameter on the request.
 *
 * The last part of the drimage request URL is the full path to the original
 * file. This is so .htaccess can rewrite this URL if the derived image
 * already exists and serve it directly from disk without having to
 * bootstrap Drupal.
 */
class PathProcessorImageStyles implements InboundPathProcessorInterface {

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Constructs a new PathProcessorImageStyles object.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   */
  public function __construct(StreamWrapperManagerInterface $stream_wrapper_manager) {
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    // @todo: shorten this, don't need $path_prefix as variable.
    if (strpos($path, '/drimage/') === 0) {
      $path_prefix = '/drimage/';
    }
    else {
      return $path;
    }

    // Strip out path prefix.
    $rest = preg_replace('|^' . preg_quote($path_prefix, '|') . '|', '', $path);

    // Get the image style, scheme and path.
    if (substr_count($rest, '/') >= 4) {
      [$width, $height, $fid, $iwc_id, $file] = explode('/', $rest, 5);

      // Set the file as query parameter.
      // @todo: this doesn't seem to be used anywhere, can we remove it?
      $request->query->set('filename', $file);

      // Get the extension and pass that along as the format parameter.
      $file = explode('.', $file);
      $format = array_pop($file);

      return $path_prefix . $width . '/' . $height . '/' . $fid . '/' . $iwc_id . '/' . $format;
    }

    return $path;
  }

}
