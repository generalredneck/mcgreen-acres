<?php

namespace Drupal\commerce_timeslots\Interfaces;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an interface defining CommerceTimeSlots service class.
 *
 * @ingroup timeslot
 */
interface CommerceTimeSlotsInterface {

  /**
   * Create a booking record into the db for each order.
   *
   * @param int $order_id
   *   The order entity id.
   * @param int $timeslot_id
   *   The time slot entity id.
   * @param int $time_frame_id
   *   The time frame entity id.
   * @param string $formatted_date
   *   The formatted date string value.
   *
   * @return \Drupal\commerce_timeslots\Interfaces\TimeSlotBookingInterface|bool
   *   Return a new created booking entity.
   */
  public function setBooking(
    int $order_id,
    int $timeslot_id,
    int $time_frame_id,
    string $formatted_date
  );

  /**
   * Free the allocated booking entity.
   *
   * @param int $order_id
   *   The order entity id.
   */
  public function unsetBooking(int $order_id);

  /**
   * Get time slot entity by the given ID.
   *
   * @param int $timeslot_id
   *   The time slot id.
   *
   * @return \Drupal\commerce_timeslots\Interfaces\TimeSlotInterface|null
   *   Return the time slot entity or null.
   */
  public function getTimeSlot(int $timeslot_id);

  /**
   * Get all time slots.
   *
   * @return array
   *   The list of time slots.
   */
  public function getAllTimeSlots(): array;

  /**
   * Get the time slot configuration.
   *
   * @param \Drupal\commerce_timeslots\Interfaces\TimeSlotInterface $time_slot
   *   The time slot entity.
   *
   * @return array
   *   Return the time slot configuration data.
   */
  public function getTimeSlotConfig(TimeSlotInterface $time_slot): array;

  /**
   * Get the built time frames markup array by a given config.
   *
   * @param int $order_id
   *   The order id.
   * @param int $timeslot_id
   *   The time slot entity id.
   * @param string $date
   *   The selected date string from date picker.
   * @param string $wrapper_id
   *   The wrapper id.
   *
   * @return array
   *   Return the form time frames markup data.
   */
  public function getTimeFramesMarkup(
    int $order_id,
    int $timeslot_id,
    string $date,
    string $wrapper_id = ''
  ): array;

  /**
   * Get the list of time frames from a time slot entity and a given date.
   *
   * @param int $order_id
   *   The order id.
   * @param int $timeslot_id
   *   The time slot entity id.
   * @param string $date
   *   The selected date string from date picker.
   *
   * @return array
   *   Return the list of available time frames per selected day and time slot.
   */
  public function getAvailableTimeFrames(
    int $order_id,
    int $timeslot_id,
    string $date
  ): array;

  /**
   * Get the time slot form elements array.
   *
   * @param int $timeslot_id
   *   The time slot entity id.
   * @param array $data
   *   The default values for form (if any).
   *
   * @return array
   *   Return the generated array form data for time slots.
   */
  public function getForm(int $timeslot_id, array $data = []): array;

  /**
   * Format time slot into a readable string.
   *
   * @param int $time_frame
   *   The time slot time frame id.
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The date object.
   * @param string $date_format
   *   The date format to show.
   * @param array $options
   *   (optional) An associative array of additional options, with the following
   *   elements:
   *   - 'langcode' (defaults to the current language): A language code, to
   *     translate to a language other than what is used to display the page.
   *   - 'context' (defaults to the empty context): The context the source
   *     string belongs to.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Return a rendered time slot information.
   */
  public function renderTimeSlot(
    int $time_frame,
    DrupalDateTime $date,
    string $date_format = 'd-m-Y',
    array $options = []
  ): TranslatableMarkup;

  /**
   * Format time slot into a readable string.
   *
   * @param int $time_frame
   *   The time slot time frame id.
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The date object.
   * @param string $date_format
   *   The date format to show.
   *
   * @return array
   *   Return a timeslot data in array format.
   */
  public function getTimeSlotToArray(
    int $time_frame,
    DrupalDateTime $date,
    string $date_format = 'd.m.Y'
  ): array;

}
