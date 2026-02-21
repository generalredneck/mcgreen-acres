<?php

namespace Drupal\commerce_timeslots\Controller;

use Drupal\commerce_timeslots\Services\CommerceTimeSlots;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The CommerceTimeslotsController controller class.
 */
class CommerceTimeslotsController extends ControllerBase {

  /**
   * The time selector wrapper id.
   *
   * @var string
   */
  protected string $timeWrapper = 'timeslot-time-wrapper';

  /**
   * The commerce time slots service.
   *
   * @var \Drupal\commerce_timeslots\Services\CommerceTimeSlots
   */
  protected CommerceTimeSlots $commerceTimeSlots;

  /**
   * Constructs a CommerceTimeslotsController.
   *
   * @param \Drupal\commerce_timeslots\Services\CommerceTimeSlots $commerce_timeslots
   *   The commerce time slots service.
   */
  public function __construct(CommerceTimeSlots $commerce_timeslots) {
    $this->commerceTimeSlots = $commerce_timeslots;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_timeslots.timeslots')
    );
  }

  /**
   * Ajax processor for availability of the time slot time frames.
   *
   * @param string $element_name
   *   The element name.
   * @param string $element_id
   *   The element id.
   * @param int $order_id
   *   The order id.
   * @param int $timeslot_id
   *   The time slot entity id.
   * @param string $date
   *   The date string value from the date picker.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Returns an ajax response.
   */
  public function getAvailability(string $element_name, string $element_id, int $order_id, int $timeslot_id, string $date): AjaxResponse {
    $response = new AjaxResponse();
    // Get the available list of time frames for the selected date and time slot
    // entity by the end user.
    $form = $this->commerceTimeSlots->getTimeFramesMarkup($order_id, $timeslot_id, $date, $element_id);
    $form['time']['#name'] = $element_name;

    $response->addCommand(new ReplaceCommand("#$element_id", $form));
    return $response;
  }

}
