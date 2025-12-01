<?php

namespace Drupal\commerce_timeslots;

use Drupal\commerce_timeslots\Interfaces\TimeSlotDayCapacityInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for time slot days.
 */
class TimeSlotDaysListBuilder extends EntityListBuilder {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The Drupal renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * Constructs a new TimeSlotDaysListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Drupal renderer.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter,
    RendererInterface $renderer
  ) {
    parent::__construct($entity_type, $entity_type_manager->getStorage($entity_type->id()));
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'id' => $this->t('ID'),
      'timeslot_day' => $this->t('Day'),
      'timeslot_day_capacity_ids' => $this->t('Day capacities'),
      'author' => $this->t('Author'),
      'timeslotday_type' => $this->t('Day type'),
      'created' => $this->t('Created'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $timeslot_day_capacity_ids = $entity->timeslot_day_capacity_ids;
    $timeslot_day_capacities = [];
    if (count($timeslot_day_capacity_ids)) {
      foreach ($timeslot_day_capacity_ids as $timeslot_day_capacity) {
        if (!$timeslot_day_capacity->entity instanceof TimeSlotDayCapacityInterface) {
          $timeslot_day_capacities[] = $this->t('Deleted');
        }
        else {
          $timeslot_day_capacities[] = $timeslot_day_capacity->entity->label();
        }
      }
    }

    $timeslot_day_capacities_list = [
      '#theme' => 'item_list',
      '#items' => $timeslot_day_capacities,
    ];

    /** @var \Drupal\commerce_timeslots\Entity\TimeSlotDay $entity */
    $row = [
      'id' => $entity->id(),
      'timeslot_day' => $entity->getTimeSlotDay() . ' : ' . $entity->label(),
      'timeslot_day_capacity_ids' => $this->renderer->render($timeslot_day_capacities_list),
      'author' => [
        'data' => [
          '#theme' => 'username',
          '#account' => $entity->getOwner(),
        ],
      ],
      'timeslotday_type' => $entity->getTimeSlotDayType(),
      'created' => $this
        ->dateFormatter
        ->format($entity->getCreatedTime(), 'short'),
    ];

    return $row + parent::buildRow($entity);
  }

}
