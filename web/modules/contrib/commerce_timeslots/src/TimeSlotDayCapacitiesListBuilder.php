<?php

namespace Drupal\commerce_timeslots;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for time slot day capacities.
 */
class TimeSlotDayCapacitiesListBuilder extends EntityListBuilder {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs a new TimeSlotDayCapacitiesListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter
  ) {
    parent::__construct($entity_type, $entity_type_manager->getStorage($entity_type->id()));
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'capacity' => $this->t('Capacity'),
      'interval' => $this->t('Interval'),
      'author' => $this->t('Author'),
      'created' => $this->t('Created'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $interval = '';
    /** @var \Drupal\commerce_timeslots\Entity\TimeSlotDayCapacity $entity */
    if (!$entity->interval->isEmpty()) {
      $format = 'H:i';
      $interval = $this->t('from @from to @to', [
        '@from' => $this
          ->dateFormatter
          ->format(
            strtotime($entity->interval->value),
            'custom',
            $format,
            date_default_timezone_get()
        ),
        '@to' => $this
          ->dateFormatter
          ->format(
            strtotime($entity->interval->end_value),
            'custom',
            $format,
            date_default_timezone_get()
        ),
      ]);
    }

    $row = [
      'id' => $entity->id(),
      'name' => $entity->label(),
      'capacity' => $entity->capacity->value,
      'interval' => $interval,
      'author' => [
        'data' => [
          '#theme' => 'username',
          '#account' => $entity->getOwner(),
        ],
      ],
      'created' => $this
        ->dateFormatter
        ->format($entity->getCreatedTime(), 'short'),
    ];

    return $row + parent::buildRow($entity);
  }

}
