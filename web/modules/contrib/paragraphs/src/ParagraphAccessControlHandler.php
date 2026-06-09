<?php

namespace Drupal\paragraphs;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableRevisionableStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access controller for the paragraphs entity.
 *
 * @see \Drupal\paragraphs\Entity\Paragraph.
 */
class ParagraphAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * Contains the configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a TranslatorAccessControlHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config object factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $paragraph, $operation, AccountInterface $account) {
    // Allowed when the operation is not view or the status is true.
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $config = $this->configFactory->get('paragraphs.settings');
    if ($operation === 'view') {
      $access_result = AccessResult::allowedIf($paragraph->isPublished() || ($account->hasPermission('view unpublished paragraphs') && $config->get('show_unpublished')))->addCacheableDependency($config);
    }
    else {
      $access_result = AccessResult::allowed();
    }
    $parent_entity = $paragraph->getParentEntity();
    if ($paragraph->getParentEntity() != NULL) {
      // Delete permission on the paragraph, should just depend on 'update'
      // access permissions on the parent.
      $operation = ($operation === 'delete') ? 'update' : $operation;
      // Library items have no support for parent entity access checking.
      if ($parent_entity->getEntityTypeId() == 'paragraphs_library_item') {
        return $access_result;
      }
      // Paragraphs on blocks and paragraphs can get into a state that makes it
      // difficult to find the correct revision of the parent to check access
      // against, so return the current access result in that case.
      $skip_types = ['block_content', 'paragraph'];
      if (($operation == 'view' || $operation == 'update') && in_array($parent_entity->getEntityTypeId(), $skip_types)) {
        return $access_result;
      }
      if ($operation !== 'view') {
        $storage = $this->entityTypeManager->getStorage($parent_entity->getEntityTypeId());
        // Load the latest revision if the parent entity is not new and is revisionable.
        if ($storage instanceof TranslatableRevisionableStorageInterface && !$parent_entity->isNew()) {
          $parent_entity_langcode = $parent_entity->language()->getId();
          $parent_entity_id = $storage->getLatestTranslationAffectedRevisionId($parent_entity->id(),
            $parent_entity_langcode);
          if ($parent_entity_id) {
            $parent_entity = $storage->loadRevision($parent_entity_id);
            if ($parent_entity && $parent_entity->hasTranslation($parent_entity_langcode)) {
              $parent_entity = $parent_entity->getTranslation($parent_entity_langcode);
            }
          }
        }
      }
      // Now check access on the paragraph's parent.
      $parent_access = $parent_entity->access($operation, $account, TRUE);
      $access_result = $access_result->orIf($parent_access);
    }
    return $access_result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Allow paragraph entities to be created in the context of entity forms.
    if (\Drupal::requestStack()->getCurrentRequest()->getRequestFormat() === 'html') {
      return AccessResult::allowed()->addCacheContexts(['request_format']);
    }
    return AccessResult::neutral()->addCacheContexts(['request_format']);
  }

}
