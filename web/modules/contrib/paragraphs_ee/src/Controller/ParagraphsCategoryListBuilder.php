<?php

namespace Drupal\paragraphs_ee\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\paragraphs_ee\ParagraphsCategoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Paragraph category entities.
 */
class ParagraphsCategoryListBuilder extends DraggableListBuilder implements FormInterface {

  use AutowiredInstanceTrait;

  /**
   * {@inheritdoc}
   */
  protected $entitiesKey = 'categories';

  /**
   * Constructs a new ParagraphsCategoryListBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $config_factory,
    MessengerInterface $messenger,
  ) {
    $storage = $this->entityTypeManager->getStorage($entity_type->id());
    parent::__construct($entity_type, $storage);

    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return static::createInstanceAutowired($container, $entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'paragraphs_category_admin_overview';
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int|string, mixed>
   *   The header row.
   */
  public function buildHeader(): array {
    $header = [];
    $header['label'] = $this->t('Label');
    $header['description'] = $this->t('Description');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int|string, mixed>
   *   The entity row.
   */
  public function buildRow(EntityInterface $entity): array {
    $row = [];
    $row['label'] = $entity->label();
    if ($entity instanceof ParagraphsCategoryInterface) {
      $row['description'] = [
        'data' => [
          '#markup' => $entity->getDescription(),
        ],
      ];
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<int|string, mixed>
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    /** @var array<string, mixed> $actions */
    $actions = $form['actions'] ?? [];
    /** @var array<string, mixed> $submit */
    $submit = $actions['submit'] ?? [];
    $submit['#value'] = $this->t('Save');
    $actions['submit'] = $submit;
    $form['actions'] = $actions;
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<int|string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $this->messenger->addStatus($this->t('The Paragraphs category ordering has been saved.'));
  }

}
