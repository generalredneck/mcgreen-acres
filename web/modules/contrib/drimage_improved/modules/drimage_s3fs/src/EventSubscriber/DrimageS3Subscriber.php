<?php

declare(strict_types=1);

namespace Drupal\drimage_s3fs\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\drimage_improved\DrimageManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Cache\Cache;

/**
 * Subscribes to kernel request event so it handles non-existing images.
 */
final class DrimageS3Subscriber implements EventSubscriberInterface {

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The Drimage manager.
   *
   * @var \Drupal\drimage_improved\DrimageManagerInterface
   */
  protected $drimageManager;

  /**
   * Constructs a new DrimageRoutes object.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger factory.
   * @param \Drupal\drimage_improved\DrimageManagerInterface $drimage_manager
   *  The Drimage manager.
   */
  public function __construct(StreamWrapperManagerInterface $stream_wrapper_manager, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, DrimageManagerInterface $drimage_manager) {
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory->get('drimage_improved');
    $this->drimageManager = $drimage_manager;
  }

  /**
   * Kernel request event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onKernelRequest(RequestEvent $event) {
    // Check if the request contains /s3/files/.
    $path = $event->getRequest()->getPathInfo();

    if (strpos($path, '/s3/files/') !== FALSE && strpos($path, '/styles/drimage_improved_')) {
      $config = $this->configFactory->get('drimage_improved.settings');
      $directory_path = 's3/files';
      // Remove the directory path from the path.
      $path = str_replace('/' . $directory_path, '', $path);
      $parts = explode('/', $path);
      $style = $parts[2];
      // Split style and get width and height.
      $style_parts = explode('_', $style);
      $scheme = $parts[3];
      $iwc_id = '-';
      if ($this->moduleHandler->moduleExists('image_widget_crop') && isset($style_parts[3])) {
        $width = $style_parts[1];
        $height = $style_parts[2];
        // Need to implode all parts from index 3 and further to get the correct iwc_id.
        // The image widget crop id itself can contain underscores.
        $iwc_id = implode('_', array_slice($style_parts, 3));
      }
      elseif ($this->moduleHandler->moduleExists('focal_point')) {
        $width = $style_parts[3];
        $height = $style_parts[4];
      }
      else {
        $width = $style_parts[1];
        $height = $style_parts[2];
      }

      // Get the file path.
      $file_path = substr($path, strpos($path, $scheme) + strlen($scheme) + 1);
      $file_name = $file_path;
      if ($config->get('imageapi_optimize_webp') || $config->get('core_webp')) {
        // Remove the extra .webp in the filename.
        if (preg_match('/\.[a-zA-Z]{3,4}\.webp$/i', $file_name)) {
          $file_name = substr($file_name, 0, (strrpos($file_name, '.')));
        }
      }
      // Redirect to drimage_improved.image route.
      $image = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $scheme . '://' . urldecode($file_name)]);
      $filename = end($parts);
      $filename_parts = explode('.', $filename);
      $format = end($filename_parts);

      // Check if the image was found.
      if (empty($image)) {
        // Log an error message if the image is not found.
        $this->loggerFactory->error('Image not found: @uri', ['@uri' => $scheme . '://' . urldecode($file_name)]);

        // Throw a 404 error.
        return new Response('Error generating image, missing source file.', 404);
      }

      // Get the first (and presumably only) image entity.
      $images = reset($image);

      // Check if the image entity is valid and has a valid ID.
      if (!$images || !$images->id()) {
        // Log an error message if the fid is not valid.
        $this->loggerFactory->error('Invalid file ID for image: @uri', ['@uri' => $scheme . '://' . urldecode($file_name)]);

        // Throw a 404 error.
        return new Response('Error generating image, missing source file.', 404);
      }

      // Deliver the image.
      $this->drimageManager->image($event->getRequest(), (int) $width, (int) $height, (int) end($image)->id(), $iwc_id, $format);

      // Add a cache tag based on the file ID to allow for targeted cache invalidation.
      $tags[] = 'file:' . $images->id();

      // Invalidate the cache for the specified tags to ensure updated content is loaded.
      Cache::invalidateTags($tags);

    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onKernelRequest'],
    ];
  }

}
