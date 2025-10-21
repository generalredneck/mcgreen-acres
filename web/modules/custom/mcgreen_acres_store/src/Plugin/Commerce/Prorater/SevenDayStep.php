<?php

namespace Drupal\mcgreen_acres_store\Plugin\Commerce\Prorater;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\RounderInterface;
use Drupal\commerce_recurring\BillingPeriod;
use Drupal\commerce_recurring\Plugin\Commerce\Prorater\ProraterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides proportional prorating at defined interval steps.
 *
 * @CommerceProrater(
 *   id = "seven_day_step",
 *   label = @Translation("Seven Day Step"),
 * )
 */
class SevenDayStep extends ProraterBase implements ContainerFactoryPluginInterface {

  /**
   * The price rounder service.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * Constructs a new Proportional object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The rounder.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RounderInterface $rounder
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->rounder = $rounder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('commerce_price.rounder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'steps' => 4,
      'step_interval' => [
        'period' => 'week',
        'interval' => '1',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prorateOrderItem(OrderItemInterface $order_item, BillingPeriod $partial_period, BillingPeriod $period) {
    // Don't do anything if the two periods are the same duration.
    if ($partial_period->getDuration() == $period->getDuration()) {
      return $order_item->getUnitPrice();
    }

    $full_period_start_date = $period->getStartDate();
    $partial_period_start_date = $partial_period->getStartDate();

    // Handle a rollover schedule.
    $billing_schedule = $order_item->getPurchasedEntity()->billing_schedule->entity;
    if ($billing_schedule->getPluginId() == 'fixed_with_free_rollover') {
      // If the partial period start date falls outside the full period, then
      // this is a rollover.
      // E.g., A start date in December for a 1 Jan yearly schedule with 1 month
      // rollover gives us a partial period of slightly more than a year, which
      // starts before the first billing period, which starts on the following
      // 1 Jan and runs for the year.
      if (!$period->contains($partial_period_start_date)) {
        // Return the full price.
        return $order_item->getUnitPrice();
      }
    }

    // The number of steps the interval is divided into.
    $total_step_count = 4;

    $step_interval = $this->getStepDateInterval();

    // Determine which step the start of the partial period falls in.
    // Start at the beginning of the full period, and add the step interval
    // until we get a date that falls inside the partial period, or we run out
    // of steps to add.
    $step_start_date = $full_period_start_date;
    $step_count = 0;
    do {
      $step_start_date->add($step_interval);
      $step_count++;
    }
    // Adding the step interval for the final step will actually take us to a
    // date past the period, since it will take us to the start of the next
    // one. Therefore, we have to also check we don't go past the total step
    // count.
    while (!$partial_period->contains($step_start_date) && $step_count < $total_step_count);

    // Calculate the prorated price based on the step we are in. If we are in
    // a partial step, we skip it. We only want to charge for full steps
    // remaining.
    $price = $order_item->getUnitPrice();

    // Get the number of full or partial steps remaining.
    // E.g., if we are in step 2 of 4, there are 3 steps remaining: the rest
    // of step 2, then steps 3 and 4.
    $remaining_steps = $total_step_count - $step_count + 1;

    // Multiple the price by the ratio of remaining steps to total steps.
    $ratio = Calculator::divide($remaining_steps, $total_step_count);
    $price = $price->multiply($ratio);

    $price = $this->rounder->round($price);

    return $price;
  }


  /**
   * Gets a DateInterval object for this plugin's step configuration.
   *``
   * @return \DateInterval
   *   The DateInterval object representing the configured interval.
   */
  protected function getStepDateInterval() {
    $config = $this->getConfiguration();

    $interval_configuration = $config['step_interval'];
    // The interval plugin ID is the 'period' value.
    $interval_plugin_id = $interval_configuration['period'];

    // Create a DateInterval that represents the interval.
    // TODO: This can be removed when https://www.drupal.org/node/2900435 lands.
    $interval_plugin_definition = \Drupal::service('plugin.manager.interval.intervals')->getDefinition($interval_plugin_id);
    $value = $interval_configuration['interval'] * $interval_plugin_definition['multiplier'];
    $date_interval = \DateInterval::createFromDateString($value . ' ' . $interval_plugin_definition['php']);

    return $date_interval;
  }

}
