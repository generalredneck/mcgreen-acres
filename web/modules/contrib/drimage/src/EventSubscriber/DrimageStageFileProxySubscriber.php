<?php

namespace Drupal\drimage\EventSubscriber;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\PathProcessor\PathProcessorManager;
use Drupal\file\Entity\File;
use Drupal\stage_file_proxy\EventSubscriber\StageFileProxySubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Decorates the stage file proxy subscriber for controller requests.
 *
 * Allows stage file proxy to fetch the correct source image by passing an uri
 * in the format it expects.
 */
class DrimageStageFileProxySubscriber implements EventSubscriberInterface {

  /**
   * The decorated stage file proxy event subscriber.
   *
   * @var \Drupal\stage_file_proxy\EventSubscriber\StageFileProxySubscriber
   */
  protected StageFileProxySubscriber $inner;

  /**
   * The path processor service.
   *
   * @var \Drupal\Core\PathProcessor\PathProcessorManager
   */
  protected PathProcessorManager $pathProcessorManager;

  /**
   * The http kernel service.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected HttpKernelInterface $kernel;

  /**
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected ImageFactory $imageFactory;

  /**
   * The file url generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs a new DrimageStageFileProxySubscriber.
   *
   * @param \Drupal\stage_file_proxy\EventSubscriber\StageFileProxySubscriber $inner
   *   The decorated stage file proxy event subscriber.
   * @param \Drupal\Core\PathProcessor\PathProcessorManager $pathProcessorManager
   *   The path processor service.
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $kernel
   *   The http kernel service.
   * @param \Drupal\Core\Image\ImageFactory $imageFactory
   *   The image factory service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file url generator service.
   */
  public function __construct(
    StageFileProxySubscriber $inner,
    PathProcessorManager $pathProcessorManager,
    HttpKernelInterface $kernel,
    ImageFactory $imageFactory,
    FileUrlGeneratorInterface $fileUrlGenerator
  ) {
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->imageFactory = $imageFactory;
    $this->kernel = $kernel;
    $this->inner = $inner;
    $this->pathProcessorManager = $pathProcessorManager;
  }

  /**
   * Provides support for drimage uri.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function checkFileOrigin(RequestEvent $event): void {
    $request = $event->getRequest();

    // Process to request uri, mainly to filter out language prefixes.
    $request_uri = $this->pathProcessorManager->processInbound(
      $request->getRequestUri(),
      clone $request
    );

    if (substr($request_uri, 0, 8 ) === '/drimage') {
      [,,,,$fid] = explode('/', $request_uri);
      $file = File::load($fid);

      // The expected location is only known for managed files.
      if ($file !== NULL) {
        $image = $this->imageFactory->get($file->getFileUri());

        // Nothing to do if dealing with a valid local image.
        if ($image->isValid()) {
          return;
        }

        // Create a new request object with an uri that can be taken care of by
        // stage file proxy.
        $request = Request::create($this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()));
        $event = new RequestEvent(
          $this->kernel, $request, $event->getRequestType()
        );

      }
    }

    // Call the decorated service, either with the original event, or the one
    // created above.
    $this->inner->checkFileOrigin($event);
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return StageFileProxySubscriber::getSubscribedEvents();
  }

}
