<?php

namespace Drupal\file_to_media;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\media\MediaTypeInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a trait for file to media access concerns.
 */
trait FileToMediaAccessTrait {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Handle dependency injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container.
   */
  protected function doCreate(ContainerInterface $container) {
    $this->entityTypeManager = $container->get('entity_type.manager');
  }

  /**
   * Checks if the user has access to create media of the given type.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   Media type to check.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Access result.
   */
  protected function hasCreateAccessToMediaType(MediaTypeInterface $media_type) : AccessResultInterface {
    $access_handler = $this->entityTypeManager->getAccessControlHandler('media');
    return $access_handler->createAccess($media_type->id(), NULL, [], TRUE);
  }

  /**
   * Checks if the source field supports files of the given type.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param string $extension
   *   File extension.
   *
   * @return bool
   *   TRUE if the field supports files.
   */
  protected function sourceFieldIsCompatible(FieldDefinitionInterface $field_definition, string $extension) : bool {
    $extensions = explode(' ', $field_definition->getSetting('file_extensions') ?: '');
    return is_a($field_definition->getItemDefinition()->getClass(), FileItem::class, TRUE) && in_array($extension, $extensions, TRUE);
  }

  /**
   * Checks if a file is downloadable to public.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to check.
   *
   * @return bool
   *   TRUE if the file is a public accessible file, otherwise return FALSE.
   */
  protected function isPublicDownloadable(FileInterface $file) {
    return $file->access('download', User::load(0));
  }

}
