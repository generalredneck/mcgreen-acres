<?php

declare(strict_types=1);

namespace Drupal\recipe_tracker\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\recipe_tracker\LogInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the log entity class.
 *
 * @ContentEntityType(
 *   id = "recipe_tracker_log",
 *   label = @Translation("Recipe Log"),
 *   label_collection = @Translation("Recipe Tracker Logs"),
 *   label_singular = @Translation("recipe log"),
 *   label_plural = @Translation("recipe logs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count log",
 *     plural = "@count logs",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\recipe_tracker\LogListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" =
 *   "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "recipe_tracker_log",
 *   admin_permission = "administer recipe_tracker_log",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "recipe_label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/modules/recipe-log",
 *     "canonical" = "/admin/modules/recipe-log/{recipe_tracker_log}",
 *     "delete-form" = "/admin/modules/recipe-log/{recipe_tracker_log}/delete",
 *     "delete-multiple-form" = "/admin/modules/recipe-log/delete-multiple",
 *   },
 * )
 */
final class Log extends ContentEntityBase implements LogInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setRecipeName(string $recipe): self {
    $this->set('recipe_label', $recipe);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setPackageName(string $packageName): self {
    $this->set('package_name', $packageName);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setVersion(string $version): self {
    $this->set('version', $version);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['recipe_label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Recipe label'))
      ->setDescription(t('The recipe name as appears in recipe.yml at the time of application.'))
      ->setRequired(TRUE)
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['package_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Package name'))
      ->setDescription(t('The fully qualified name of the package where recipe was located.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['version'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Version'))
      ->setDescription(t('The fully version of the composer package.'))
      ->setDefaultValue('@dev')
      ->setRequired(TRUE)
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', 32)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => 1,
      ]);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user who applied the recipe'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Applied on'))
      ->setDescription(t('The time that the recipe was applied.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
