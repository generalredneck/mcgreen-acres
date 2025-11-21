<?php

namespace Drupal\commerce_timeslots;

use Drupal\commerce_timeslots\Interfaces\TimeSlotDayInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for time slots.
 */
class TimeSlotsListBuilder extends EntityListBuilder {

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
   * Constructs a new TimeSlotsListBuilder object.
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
      'name' => $this->t('Name'),
      'timeslot_day_ids' => $this->t('Time slot days'),
      'author' => $this->t('Author'),
      'created' => $this->t('Created'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $timeslot_day_ids = $entity->timeslot_day_ids;
    $timeslot_days = [];
    if (count($timeslot_day_ids)) {
      foreach ($timeslot_day_ids as $timeslot_day) {
        if (!$timeslot_day->entity instanceof TimeSlotDayInterface) {
          $timeslot_days[] = $this->t('Deleted');
        }
        else {
          $day_type = $timeslot_day->entity->timeslotday_type->value;
          $timeslot_days[] = $day_type . ' : ' . $timeslot_day->entity->label();
        }
      }
    }

    $timeslot_days_list = [
      '#theme' => 'item_list',
      '#items' => $timeslot_days,
    ];

    /** @var \Drupal\commerce_timeslots\Entity\TimeSlot $entity */
    $row = [
      'id' => $entity->id(),
      'name' => $entity->label(),
      'timeslot_day_ids' => $this->renderer->render($timeslot_days_list),
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
    $row += parent::buildRow($entity);

    if (!empty($row['operations']['data'])) {
      $row['operations']['data']['#links']['view'] = [
        'title' => $this->t('View'),
        'weight' => -10,
        'url' => $entity->toUrl('canonical'),
      ];
    }

    return $row;
  }

}
