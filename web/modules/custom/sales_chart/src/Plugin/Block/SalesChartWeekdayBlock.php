<?php

namespace Drupal\sales_chart\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a chart of average daily revenue by day of week.
 *
 * Meant to be placed via Block Layout on the "Sales report" view's "Daily"
 * display (views.view.sales_report, display id "daily"). It reads the same
 * exposed-filter GET params the view uses — date[min]/date[max] and
 * order_type[] — so the chart stays in sync with whatever range/order-type
 * combination is currently applied to the report table. If placed on a page
 * without that exposed filter form, it falls back to its configured
 * rolling-days window with no order-type filtering. See queryWeekdayTotals()
 * and getSiteTimezone() for the day-bucketing/timezone logic, which mirrors
 * SalesChartBlock and the "Sales Report" view.
 *
 * @Block(
 *   id = "sales_chart_weekday_block",
 *   admin_label = @Translation("Sales Chart - Average by Day of Week"),
 *   category = @Translation("Commerce")
 * )
 */
class SalesChartWeekdayBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * ISO weekday order (Monday first), keyed by DateTime 'N' format value.
   */
  protected const WEEKDAY_LABELS = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday',
  ];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly Connection $database,
    protected readonly RequestStack $requestStack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('request_stack'),
    );
  }

  /**
   * Returns the resolved site/user timezone.
   *
   * Mirrors SalesChartBlock::getSiteTimezone() so day-bucketing (and
   * therefore day-of-week bucketing) stays in step with the "Sales Report"
   * view.
   */
  protected function getSiteTimezone(): \DateTimeZone {
    return new \DateTimeZone(date_default_timezone_get());
  }

  public function defaultConfiguration(): array {
    return [
      'days' => 90,
      'currency_code' => 'USD',
    ];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['days'] = [
      '#type' => 'number',
      '#title' => $this->t('Fallback days to display'),
      '#description' => $this->t('Used only when no date[min]/date[max] exposed filter is present in the URL.'),
      '#default_value' => $config['days'],
      '#min' => 7,
      '#max' => 730,
    ];

    $form['currency_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Currency code'),
      '#default_value' => $config['currency_code'],
      '#size' => 10,
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->setConfigurationValue('days', (int) $form_state->getValue('days'));
    $this->setConfigurationValue('currency_code', $form_state->getValue('currency_code'));
  }

  public function getCacheContexts(): array {
    // Vary the cached block output whenever date[min]/date[max] or order_type
    // change.
    return array_merge(parent::getCacheContexts(), [
      'url.query_args:date',
      'url.query_args:order_type',
    ]);
  }

  public function build(): array {
    $config = $this->getConfiguration();
    $days = (int) $config['days'];
    $currency = $config['currency_code'];

    // Honour date[min] / date[max] query params (same identifiers used by the
    // Views sales report exposed date filter) when present, otherwise fall
    // back to the configured rolling-days window.
    $date_params = $this->requestStack->getCurrentRequest()->query->all('date');
    $min_raw = $date_params['min'] ?? NULL;
    $max_raw = $date_params['max'] ?? NULL;

    // Honour the order_type[] query param (same identifier as the Views
    // sales report's exposed "Order type" filter).
    $order_types = $this->requestStack->getCurrentRequest()->query->all('order_type');
    $order_types = array_filter(array_map('strval', $order_types));

    $timezone = $this->getSiteTimezone();

    try {
      $start = $min_raw ? new \DateTime($min_raw, $timezone) : NULL;
      $end   = $max_raw ? new \DateTime($max_raw, $timezone) : NULL;
    }
    catch (\Exception) {
      $start = $end = NULL;
    }

    if (!$start || !$end || $start > $end) {
      $end   = new \DateTime('today', $timezone);
      $start = (clone $end)->modify("-{$days} days");
    }

    $daily_raw = $this->queryDailyTotals($start, $end, $currency, $timezone, $order_types);
    $date_series = $this->generateDateSeries($start, $end);

    // Fill every day in the range; missing days become 0 so weekdays with no
    // sales still count toward the average for that weekday.
    $daily_data = [];
    foreach ($date_series as $date) {
      $daily_data[$date] = $daily_raw[$date] ?? 0.0;
    }

    $weekday_stats = $this->aggregateByWeekday($daily_data, $timezone);
    $chart_data = $this->buildChartData($weekday_stats, $currency);

    $busiest_key = NULL;
    foreach ($weekday_stats as $key => $stat) {
      if ($busiest_key === NULL || $stat['avg'] > $weekday_stats[$busiest_key]['avg']) {
        $busiest_key = $key;
      }
    }

    return [
      '#theme' => 'sales_chart_weekday',
      '#stats' => [
        'currency' => $currency,
        'date_range' => $start->format('M j, Y') . ' – ' . $end->format('M j, Y'),
        'days_analyzed' => count($date_series),
        'busiest_day' => $busiest_key !== NULL ? self::WEEKDAY_LABELS[$busiest_key] : NULL,
        'busiest_day_avg' => $busiest_key !== NULL ? number_format($weekday_stats[$busiest_key]['avg'], 2) : '0.00',
      ],
      '#attached' => [
        'library' => ['sales_chart/weekday_chart'],
        'drupalSettings' => ['salesChartWeekday' => $chart_data],
      ],
    ];
  }

  /**
   * Queries commerce_order_report for daily revenue totals.
   *
   * Identical bucketing logic to SalesChartBlock::queryDailyTotals() — see
   * that method's docblock for why the FROM_UNIXTIME() offset shift is
   * needed to match the "Sales Report" view's day boundaries.
   */
  protected function queryDailyTotals(\DateTime $start, \DateTime $end, string $currency, \DateTimeZone $timezone, array $order_types = []): array {
    $end_exclusive = (clone $end)->modify('+1 day');
    $offset_seconds = $timezone->getOffset($start);

    $join = '';
    $order_type_condition = '';
    $args = [
      ':currency' => $currency,
      ':offset' => $offset_seconds,
      ':start' => $start->getTimestamp(),
      ':end' => $end_exclusive->getTimestamp(),
    ];
    if ($order_types) {
      // Mirrors the Views sales report's "Order type" exposed filter, which
      // filters commerce_order.type via the order_id relationship.
      $join = "INNER JOIN {commerce_order} o ON o.order_id = r.order_id";
      $order_type_condition = 'AND o.type IN (:order_types[])';
      $args[':order_types[]'] = $order_types;
    }

    $results = $this->database->query(
      "SELECT DATE_FORMAT(FROM_UNIXTIME(r.created + :offset), '%Y-%m-%d') AS day,
              SUM(a.amount_number) AS total
       FROM {commerce_order_report} r
       INNER JOIN {commerce_order_report__amount} a
         ON a.entity_id = r.report_id AND a.deleted = 0
       $join
       WHERE r.type = 'order_report'
         AND a.amount_currency_code = :currency
         AND r.created >= :start
         AND r.created < :end
         $order_type_condition
       GROUP BY day
       ORDER BY day ASC",
      $args
    );

    $data = [];
    foreach ($results as $row) {
      $data[$row->day] = (float) $row->total;
    }
    return $data;
  }

  /**
   * Returns an array of Y-m-d strings for every day in the range.
   */
  protected function generateDateSeries(\DateTime $start, \DateTime $end): array {
    $series = [];
    $current = clone $start;
    while ($current <= $end) {
      $series[] = $current->format('Y-m-d');
      $current->modify('+1 day');
    }
    return $series;
  }

  /**
   * Groups daily totals by ISO weekday (1 = Monday ... 7 = Sunday).
   *
   * Returns an array keyed by ISO weekday number, ordered Monday through
   * Sunday, each with 'total', 'days', and 'avg'. Every weekday is present
   * even if it had zero matching days in range.
   */
  protected function aggregateByWeekday(array $daily_data, \DateTimeZone $timezone): array {
    $aggregated = [];
    foreach (self::WEEKDAY_LABELS as $iso_day => $label) {
      $aggregated[$iso_day] = ['total' => 0.0, 'days' => 0];
    }

    foreach ($daily_data as $date => $amount) {
      $day = new \DateTime($date, $timezone);
      $iso_day = (int) $day->format('N');
      $aggregated[$iso_day]['total'] += $amount;
      $aggregated[$iso_day]['days']++;
    }

    foreach ($aggregated as &$data) {
      $data['avg'] = $data['days'] > 0 ? $data['total'] / $data['days'] : 0.0;
    }
    return $aggregated;
  }

  /**
   * Builds the data payload passed to drupalSettings for Chart.js.
   */
  protected function buildChartData(array $weekday_stats, string $currency): array {
    $labels = [];
    $avg_values = [];
    $total_values = [];
    $day_counts = [];

    foreach (self::WEEKDAY_LABELS as $iso_day => $label) {
      $labels[] = $label;
      $avg_values[] = round($weekday_stats[$iso_day]['avg'], 2);
      $total_values[] = round($weekday_stats[$iso_day]['total'], 2);
      $day_counts[] = $weekday_stats[$iso_day]['days'];
    }

    return [
      'labels' => $labels,
      'currency' => $currency,
      'avgValues' => $avg_values,
      'totalValues' => $total_values,
      'dayCounts' => $day_counts,
    ];
  }

}
