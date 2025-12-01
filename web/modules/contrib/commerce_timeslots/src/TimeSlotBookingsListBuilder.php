<?php

namespace Drupal\commerce_timeslots;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for time slot bookings.
 */
class TimeSlotBookingsListBuilder extends EntityListBuilder {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The Drupal renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new TimeSlotBookingsListBuilder object.
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
      'order_id' => $this->t('Order ID'),
      'timeslot' => $this->t('Time slot'),
      'timeslot_date' => $this->t('Time slot date'),
      'time_frame' => $this->t('Time slot frame'),
      'status' => $this->t('Status'),
      'created' => $this->t('Booked on'),
    ];
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    // Format order link.
    $order_link = NULL;
    /** @var \Drupal\commerce_timeslots\Entity\TimeSlotBooking $entity */
    if (!$entity->order_id->isEmpty()) {
      $order_id = $entity->order_id->entity->id();
      $render = [
        '#title' => $order_id,
        '#type' => 'link',
        '#url' => Url::fromRoute(
          'entity.commerce_order.canonical',
          ['commerce_order' => $order_id]
        ),
      ];
      $order_link = $this->renderer->render($render);
    }

    $timeslot_label = NULL;
    if (!$entity->timeslot_id->isEmpty()) {
      $timeslot_label = $entity->timeslot_id->entity->label();
    }

    $time_frame = NULL;
    if (!$entity->timeslot_day_capacity_id->isEmpty()) {
      $time_frame_entity = $entity->timeslot_day_capacity_id->entity;
      $format_interval = 'H:i';
      $start = $this
        ->dateFormatter
        ->format(strtotime($time_frame_entity->interval->value), 'custom', $format_interval);

      $end = $this
        ->dateFormatter
        ->format(strtotime($time_frame_entity->interval->end_value), 'custom', $format_interval);

      $time_frame = "$start : $end";
    }

    // Format an entity row.
    $row = [
      'id' => $entity->id(),
      'order_id' => $order_link,
      'timeslot' => $timeslot_label,
      'timeslot_date' => $entity->timeslot_date->value,
      'time_frame' => $time_frame,
      'status' => $entity->status->value,
      'created' => $this
        ->dateFormatter
        ->format($entity->getCreatedTime(), 'short'),
    ];

    return $row;
  }

}
