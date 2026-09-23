<?php

namespace Drupal\file_to_media\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\file_to_media\FileToMediaAccessTrait;
use Drupal\media\MediaTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller for converting a file into a media item.
 */
class FileToMedia implements ContainerInjectionInterface {

  use FileToMediaAccessTrait;

  /**
   * Entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->doCreate($container);
    $instance->entityFormBuilder = $container->get('entity.form_builder');
    return $instance;
  }

  /**
   * Generates form for creating a file from a given media entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   File being used to create new media.
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   Media type being used for the file.
   *
   * @return array
   *   Form.
   */
  public function fileToMediaForm(FileInterface $file, MediaTypeInterface $media_type) : array {
    if (!$this->hasCreateAccessToMediaType($media_type)->isAllowed()) {
      throw new AccessDeniedHttpException('You do not have permission to create media of this type');
    }
    // A media entity for a private file should not be created.
    if (!$this->isPublicDownloadable($file)) {
      throw new AccessDeniedHttpException('Media entity should not be created for a private file.');
    }

    $field_definition = $media_type->getSource()->getSourceFieldDefinition($media_type);
    $filename = $file->getFilename();
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if (!$this->sourceFieldIsCompatible($field_definition, $extension)) {
      throw new NotFoundHttpException('This media type does not support this type of file.');
    }
    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => $media_type->id(),
      'name' => $filename,
      $field_definition->getName() => $file->id(),
    ]);
    return $this->entityFormBuilder->getForm($media);
  }

  /**
   * Provides the page title for this controller.
   *
   * @param \Drupal\file\FileInterface $file
   *   File being used to create new media.
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   Media type being used for the file.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function fileToMediaTitle(FileInterface $file, MediaTypeInterface $media_type) : TranslatableMarkup {
    return new TranslatableMarkup('Create new %type from %file', [
      '%type' => $media_type->label(),
      '%file' => $file->getFilename(),
    ]);
  }

}
