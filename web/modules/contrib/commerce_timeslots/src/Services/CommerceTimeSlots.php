<?php

namespace Drupal\commerce_timeslots\Services;

use Drupal\commerce_timeslots\Entity\TimeSlotDay;
use Drupal\commerce_timeslots\Interfaces\CommerceTimeSlotsInterface;
use Drupal\commerce_timeslots\Interfaces\TimeSlotDayCapacityInterface;
use Drupal\commerce_timeslots\Interfaces\TimeSlotInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DateHelper;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

/**
 * The Commerce Time slots service class.
 */
class CommerceTimeSlots implements CommerceTimeSlotsInterface {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  /**
   * The time selector wrapper id.
   *
   * @var string
   */
  protected $timeWrapper = 'timeslot-time-wrapper';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The timeslots settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $settings;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $dateTime;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The UUID generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuidGenerator;

  /**
   * Creates an CommerceTimeSlots instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time_interface
   *   The time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_generator
   *   The UUID generator.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    TimeInterface $time_interface,
    DateFormatterInterface $date_formatter,
    UuidInterface $uuid_generator
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->dateTime = $time_interface;
    $this->dateFormatter = $date_formatter;
    $this->uuidGenerator = $uuid_generator;
    $this->settings = $config_factory->get('commerce_timeslots.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function setBooking(
    int $order_id,
    int $timeslot_id,
    int $time_frame_id,
    string $formatted_date
  ) {
    $bookings = $this
      ->entityTypeManager
      ->getStorage('commerce_timeslot_booking')
      ->loadByProperties(['order_id' => $order_id]);

    if (!empty($bookings)) {
      $this
        ->getLogger('commerce_timeslots')
        ->error(sprintf('Booking for order %s already exists.', $order_id));

      return reset($bookings);
    }

    $data = [
      'order_id' => $order_id,
      'timeslot_id' => $timeslot_id,
      'timeslot_day_capacity_id' => $time_frame_id,
      'timeslot_date' => $formatted_date,
      'status' => 'active',
    ];
    /** @var \Drupal\commerce_timeslots\Interfaces\TimeSlotBookingInterface $booking */
    $booking = $this
      ->entityTypeManager
      ->getStorage('commerce_timeslot_booking')
      ->create($data);

    $booking->save();

    return $booking;
  }

  /**
   * {@inheritdoc}
   */
  public function unsetBooking(int $order_id) {
    $booking = $this
      ->entityTypeManager
      ->getStorage('commerce_timeslot_booking')
      ->loadByProperties(['order_id' => $order_id]);

    /** @var \Drupal\commerce_timeslots\Interfaces\TimeSlotBookingInterface $booking */
    $booking = reset($booking);
    $booking->set('status', 'processed');
    $booking->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeSlot(int $timeslot_id) {
    $time_slot = $this
      ->entityTypeManager
      ->getStorage('commerce_timeslot')
      ->load($timeslot_id);

    return $time_slot ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllTimeSlots(): array {
    $time_slots = $this
      ->entityTypeManager
      ->getStorage('commerce_timeslot')
      ->loadMultiple();

    return $time_slots ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeSlotConfig(TimeSlotInterface $time_slot): array {
    $config = [];
    $format_interval = 'H:i';

    if (!empty($time_slot->timeslot_day_ids)) {
      foreach ($time_slot->timeslot_day_ids as $day) {
        $day_entity = $day->entity;
        $day = $day_entity->timeslot_day->value;

        $config[$day_entity->id()]['day'] = $day;
        $config[$day_entity->id()]['type'] = $day_entity->timeslotday_type->value;

        // Add the desired date (if available).
        if ($day_entity->timeslotday_type->value == 'desired') {
          $config[$day_entity->id()]['desired_date'] = $day_entity->desired_date->value;
          $config[$day_entity->id()]['timeslot_id'] = $time_slot->id();
        }

        if (!$day_entity->timeslot_day_capacity_ids) {
          continue;
        }

        foreach ($day_entity->timeslot_day_capacity_ids as $capacity) {
          /** @var \Drupal\commerce_timeslots\Interfaces\TimeSlotDayCapacityInterface $entity */
          $entity = $capacity->entity;

          if (empty($entity->interval->value)) {
            continue;
          }

          $start = $this
            ->dateFormatter
            ->format(strtotime($entity->interval->value), 'custom', $format_interval);

          $end = $this
            ->dateFormatter
            ->format(strtotime($entity->interval->end_value), 'custom', $format_interval);

          $config[$day_entity->id()]['capacities'][$entity->id()] = [
            'capacity' => $entity->capacity->value,
            'interval' => ['start' => $start, 'end' => $end],
          ];
        }
      }
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeFramesMarkup(
    int $order_id,
    int $timeslot_id,
    string $date,
    string $wrapper_id = ''
  ): array {
    // Get the available list of time frames for the selected date and time slot
    // entity by the end user.
    $time_frames = $this->getAvailableTimeFrames($order_id, $timeslot_id, $date);
    $options = $info = [];

    if (empty($wrapper_id)) {
      $uuid = $this->uuidGenerator->generate();
      $wrapper_id = "$this->timeWrapper--$uuid";
    }

    if (!$time_frames) {
      $form['time'] = [
        '#type' => 'select',
        '#title' => $this->t('Time interval'),
        '#options' => $options,
        '#title_display' => 'hidden',
        '#validated' => TRUE,
        '#prefix' => "<div id='$wrapper_id' class='timeslot-message'>",
        '#suffix' => $this->t('There are no available time frames for this date. Please, choose a different date.') . '</div>',
      ];
      $form['time']['#attributes']['class'][] = 'hidden';
      return $form;
    }

    foreach ($time_frames as $frame_id => $frame) {
      $options[$frame_id] = $frame['time_frame'];
      $info[$frame_id] = $frame['info'];
    }

    $form['time'] = [
      '#type' => 'select',
      '#title' => $this->t('Time frames'),
      '#options' => $options,
      '#validated' => TRUE,
      '#prefix' => "<div id='$wrapper_id'>",
      '#suffix' => '</div>',
    ];

    // Configure time ranges field attributes.
    $form['time']['#attributes']['class'][] = 'timeslots-time-range';
    // Attach the information text to the select options.
    $form['time']['#attributes']['data-info'] = Json::encode($info);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableTimeFrames(
    int $order_id,
    int $timeslot_id,
    string $date
  ): array {
    // Format the date.
    $date = (new DrupalDateTime($date, 'UTC'))->format('Y-m-d');

    $time_slot = $this
      ->entityTypeManager
      ->getStorage('commerce_timeslot')
      ->load($timeslot_id);

    if (!$time_slot instanceof TimeSlotInterface) {
      throw new NotFoundResourceException("Couldn't load the time slot entity.");
    }

    // Get all booked entries for the given time slot and date.
    $exists = $this
      ->entityTypeManager
      ->getStorage('commerce_timeslot_booking')
      ->loadByProperties([
        'order_id' => $order_id,
      ]);

    if (!empty($exists)) {
      throw new \Exception("Couldn\'t process this order.");
    }

    $timeslot_bookings = $this
      ->entityTypeManager
      ->getStorage('commerce_timeslot_booking')
      ->loadByProperties([
        'timeslot_id' => $timeslot_id,
        'timeslot_date' => Xss::filter($date),
        'status' => 'active',
      ]);

    $config = $this->getTimeSlotConfig($time_slot);
    $time_frames = [];
    // Extract the day name string from the selected date.
    $selected_day = DateHelper::dayOfWeekName($date, FALSE);
    // Get current selected day machine name.
    $selected_day_name = NULL;
    foreach (TimeSlotDay::getTimeSlotDays() as $day => $day_name) {
      if ($day_name != $selected_day) {
        continue;
      }
      $selected_day_name = $day;
    }

    $bookings = [];
    if (!empty($timeslot_bookings)) {
      foreach ($timeslot_bookings as $booking) {
        $capacity = $booking->timeslot_day_capacity_id->entity->id();
        if (!isset($bookings[$capacity])) {
          $bookings[$capacity] = 1;
        }
        else {
          $bookings[$capacity]++;
        }
      }
    }

    // Split the regular and desired dates.
    $days_desired = [];
    foreach ($config as $day_config) {
      if ($day_config['type'] == 'desired') {
        $days_desired[$day_config['desired_date']] = $day_config;
      }
    }

    // Processing a desired date.
    if (isset($days_desired[$date]) && $days_desired[$date]['timeslot_id'] == $timeslot_id) {
      foreach ($days_desired[$date]['capacities'] as $time_frame_id => $time_frame) {
        // Skip the time frame where the capacity is filled in.
        if (
          isset($bookings[$time_frame_id]) &&
          (int) $bookings[$time_frame_id] >= (int) $time_frame['capacity']
        ) {
          continue;
        }

        // Set the information about current time slot.
        $info = $this->t('You are the @current out of @total.', [
          '@current' => isset($bookings[$time_frame_id]) ? ($bookings[$time_frame_id] + 1) : 1,
          '@total' => $time_frame['capacity'],
        ]);
        // Get the time frame formatted for input.
        $frame = $time_frame['interval']['start'] . ' : '
          . $time_frame['interval']['end'];
        $time_frames[$time_frame_id]['time_frame'] = $frame;
        $time_frames[$time_frame_id]['info'] = $info;
      }

      return $time_frames;
    }

    // Processing a regular date.
    foreach ($config as $day_config) {
      if ($day_config['day'] != $selected_day_name || $day_config['type'] == 'desired') {
        continue;
      }

      foreach ($day_config['capacities'] as $time_frame_id => $time_frame) {
        // Skip the time frame where the capacity is filled in.
        if (
          isset($bookings[$time_frame_id]) &&
          (int) $bookings[$time_frame_id] >= (int) $time_frame['capacity']
        ) {
          continue;
        }

        // Set the information about current time slot.
        $info = $this->t('You are the @current out of @total.', [
          '@current' => isset($bookings[$time_frame_id]) ? ($bookings[$time_frame_id] + 1) : 1,
          '@total' => $time_frame['capacity'],
        ]);
        // Get the time frame formatted for input.
        $frame = $time_frame['interval']['start'] . ' : '
          . $time_frame['interval']['end'];
        $time_frames[$time_frame_id]['time_frame'] = $frame;
        $time_frames[$time_frame_id]['info'] = $info;
      }
    }
    return $time_frames;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(int $timeslot_id, array $data = []): array {
    $time_slot = $this->getTimeSlot($timeslot_id);
    if (!$time_slot instanceof TimeSlotInterface) {
      return [];
    }

    $order_id = !empty($data['order_id']) ? $data['order_id'] : 0;
    if (!empty($data['time_slot'])) {
      $data = $data['time_slot']['wrapper'];
    }
    $date_format = 'Y-m-d';
    // Get time slot config.
    $time_slot_config = $this->getTimeSlotConfig($time_slot);
    // Set the default date which is current date. User won't be able to select
    // a date from past.
    $default_date = DrupalDateTime::createFromTimestamp(
      $this->dateTime->getRequestTime(),
      DateTimeItemInterface::STORAGE_TIMEZONE
    );

    // Set the start day (by default is from today).
    $nr_days_from = $this->settings->get('nr_days_from') ?? 0;
    if ($nr_days_from) {
      $default_date->modify("+$nr_days_from day");
    }

    if (!empty($data['date']) && strtotime($data['date']) > (strtotime($default_date))) {
      $default_date = $data['date'];
    }
    // Get the max date value.
    $max = clone $default_date;
    $nr_days_range = $this->settings->get('nr_days_range') ?? 7;
    $max->modify("+$nr_days_range days")->format($date_format);

    $days = [];
    foreach ($time_slot_config as $day) {
      if ($day['type'] != 'regular') {
        continue;
      }
      $days[] = $day['day'];
    }
    $days = Json::encode($days);

    // Define the time slot form field wrapper.
    $form['wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select an available time slot'),
    ];
    $form['wrapper']['date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Date'),
      '#size' => 20,
      // Hide time element as we're working the date element.
      '#date_time_element' => 'none',
      // Set the default value (if available).
      '#default_value' => $default_date,
    ];
    // Configure date field attributes.
    $form['wrapper']['date']['#attributes']['class'][] = 'timeslots-date';
    $form['wrapper']['date']['#attributes']['data-order_id'][] = $order_id;
    $form['wrapper']['date']['#attributes']['data-timeslot_id'][] = $time_slot->id();
    $form['wrapper']['date']['#attributes']['data-show_days'][] = $days;
    $form['wrapper']['date']['#attributes']['min'][] = $default_date;
    $form['wrapper']['date']['#attributes']['max'][] = $max;
    $form['wrapper']['date']['#attributes']['data-drupal-date-format'] = 'Y-m-d';

    $date = $this
      ->dateFormatter
      ->format(strtotime($default_date), 'custom', $date_format);

    // Get the time frames in context of current selected date.
    $form['wrapper']['time'] = $this->getTimeFramesMarkup($order_id, $time_slot->id(), $date)['time'];
    // Set the default value (if available).
    $form['wrapper']['time']['#default_value'] = !empty($data['time']) ? $data['time'] : NULL;

    // Attach the time slot base js library to the form element. Without this
    // library we won't be able to adjust date picker days and other logic.
    $form['#attached']['library'][] = 'commerce_timeslots/timeslots';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function renderTimeSlot(
    int $time_frame,
    DrupalDateTime $date,
    string $date_format = 'd-m-Y',
    array $options = []
  ): TranslatableMarkup {

    // Try to get time range entity.
    $time_range = $this
      ->entityTypeManager
      ->getStorage('commerce_timeslot_day_capacity')
      ->load($time_frame);

    if ($time_range instanceof TimeSlotDayCapacityInterface && !$time_range->interval->isEmpty()) {
      $format = 'H:i';
      $interval = $this->t('from @from to @to',
        [
          '@from' => $this
            ->dateFormatter
            ->format(strtotime($time_range->interval->value), 'custom', $format),
          '@to' => $this
            ->dateFormatter
            ->format(strtotime($time_range->interval->end_value), 'custom', $format),
        ],
        $options
      );
    }
    return $this->t(
      'Desired date: @time_slot_date, Time interval: @time_slot_time',
      [
        '@time_slot_date' => $date->format($date_format),
        '@time_slot_time' => $interval,
      ],
      $options
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeSlotToArray(
    int $time_frame,
    DrupalDateTime $date,
    string $date_format = 'd.m.Y'
  ): array {
    /** @var \Drupal\commerce_timeslots\Interfaces\TimeSlotDayCapacityInterface $time_range */
    $time_range = $this
      ->entityTypeManager
      ->getStorage('commerce_timeslot_day_capacity')
      ->load($time_frame);

    $interval = '';
    if ($time_range instanceof TimeSlotDayCapacityInterface && !$time_range->interval->isEmpty()) {
      $format = 'H:i';
      $interval = $this->t('from @from to @to', [
        '@from' => $this
          ->dateFormatter
          ->format(strtotime($time_range->interval->value), 'custom', $format),
        '@to' => $this
          ->dateFormatter
          ->format(strtotime($time_range->interval->end_value), 'custom', $format),
      ]);
    }

    return [
      'date' => $date->format($date_format),
      'time' => $interval,
    ];
  }

}
