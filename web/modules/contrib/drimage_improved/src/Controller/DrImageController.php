<?php

namespace Drupal\drimage_improved\Controller;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\drimage_improved\DrimageManagerInterface;
use Drupal\image\Controller\ImageStyleDownloadController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Simple extension over the default image download controller.
 *
 * We inherit from it so we have all functions and logic available. We just
 * override the way the image is generated to suit the needs of the dynamically
 * generated image styles.
 *
 * Images are scaled by default but cropping can be activated on the formatter
 * settings form.
 * When cropping is not activated a height of 0 is passed to the Controller.
 */
class DrImageController extends ImageStyleDownloadController {

  /**
   * The Drimage manager.
   *
   * @var \Drupal\drimage_improved\DrimageManagerInterface
   */
  protected $drimageManager;

  public function __construct(LockBackendInterface $lock, ImageFactory $image_factory, StreamWrapperManagerInterface $stream_wrapper_manager, FileSystemInterface $file_system, DrimageManagerInterface $drimage_manager) {
    parent::__construct($lock, $image_factory, $stream_wrapper_manager, $file_system);

    $this->drimageManager = $drimage_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lock'),
      $container->get('image.factory'),
      $container->get('stream_wrapper_manager'),
      $container->get('file_system'),
      $container->get('drimage_improved.manager')
    );
  }

  /**
   * Deliver an image from the requested parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param int $width
   *   The requested width in pixels that came from the JS.
   * @param int $height
   *   The requested height in pixels that came from the JS.
   * @param int $fid
   *   The file id to render.
   * @param string|null $iwc_id
   *   (optional) The id for the image_widget crop type to use.
   * @param string|null $format
   *   (optional) The format to render the image in. Can be webp, jpg, png, ...
   *    When NULL will fallback to jpg/png format. (the default file in the filesystem)
   *    Currently only webp is actually supported as an alternative.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the file is not found.
   */
  public function image(Request $request, int $width, int $height, int $fid, $iwc_id = NULL, $format = NULL) {
    return $this->drimageManager->image($request, $width, $height, $fid, $iwc_id, $format);
  }

}
