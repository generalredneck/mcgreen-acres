<?php

namespace Drupal\sales_chart\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides an interactive sales chart block.
 *
 * This block stays visually in sync with the "Sales Report" view
 * (views.view.sales_report) only because it is placed exclusively on that
 * view's report pages, and reads the same exposed-filter GET params the view
 * uses: date[min]/date[max] and order_type[]. If this block is ever placed
 * on a page without that exposed filter form, it silently falls back to its
 * configured rolling-days window with no order-type filtering rather than
 * erroring — so placing it anywhere else will desync it from whatever data
 * is shown nearby. See queryDailyTotals() and getSiteTimezone() for the
 * matching day-bucketing/timezone logic that also mirrors the view.
 *
 * @Block(
 *   id = "sales_chart_block",
 *   admin_label = @Translation("Sales Chart"),
 *   category = @Translation("Commerce")
 * )
 */
class SalesChartBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * \Drupal\system\TimeZoneResolver sets PHP's default timezone on every
   * request from system.date config (falling back to the current user's
   * account timezone when timezone.user.configurable is enabled), which is
   * also what Views' QueryPluginBase::setupTimezone() relies on. Reading it
   * here keeps day-bucketing in step with the "Sales Report" view, which
   * buckets the same commerce_order_report data via ReportDateField.
   */
  protected function getSiteTimezone(): \DateTimeZone {
    return new \DateTimeZone(date_default_timezone_get());
  }

  public function defaultConfiguration(): array {
    return [
      'days' => 180,
      'currency_code' => 'USD',
    ];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['days'] = [
      '#type' => 'number',
      '#title' => $this->t('Days to display'),
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
    // Views sales report exposed date filter) when present, otherwise fall back
    // to the configured rolling-days window. This only keeps the chart in sync
    // with the report table because this block is placed exclusively on the
    // Sales Report view's pages (see class docblock) — it has no way to know
    // whether an exposed filter form sharing these param names is present.
    $date_params = $this->requestStack->getCurrentRequest()->query->all('date');
    $min_raw = $date_params['min'] ?? NULL;
    $max_raw = $date_params['max'] ?? NULL;

    // Honour the order_type[] query param (same identifier as the Views sales
    // report's exposed "Order type" filter) so the chart and the table below
    // it stay in sync when that filter is submitted. Empty/absent means no
    // filtering, matching the view's own default behavior.
    $order_types = $this->requestStack->getCurrentRequest()->query->all('order_type');
    $order_types = array_filter(array_map('strval', $order_types));

    // All day-bucketing below is anchored to the site's configured timezone
    // so the chart's day boundaries match the "Sales Report" view, which
    // buckets the same commerce_order_report data in this same timezone.
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

    // Fill every day in the range; missing days become 0.
    $daily_data = [];
    foreach ($date_series as $date) {
      $daily_data[$date] = $daily_raw[$date] ?? 0.0;
    }

    $weekly = $this->aggregateByPeriod($daily_data, 'week', $timezone);
    $monthly = $this->aggregateByPeriod($daily_data, 'month', $timezone);
    $chart_data = $this->buildChartData($date_series, $daily_data, $weekly, $monthly, $currency, $timezone);

    $total = array_sum($daily_data);
    $count = count($date_series);

    return [
      '#theme' => 'sales_chart',
      '#stats' => [
        'total_revenue' => number_format($total, 2),
        'daily_avg' => $count ? number_format($total / $count, 2) : '0.00',
        'days_analyzed' => $count,
        'currency' => $currency,
        'date_range' => $start->format('M j, Y') . ' – ' . $end->format('M j, Y'),
      ],
      '#attached' => [
        'library' => ['sales_chart/chart'],
        'drupalSettings' => ['salesChart' => $chart_data],
      ],
    ];
  }

  /**
   * Queries commerce_order_report for daily revenue totals.
   *
   * FROM_UNIXTIME() converts using MySQL's own session timezone, which does
   * not necessarily match Drupal's configured site timezone. We shift the
   * raw timestamp by the site timezone's offset before formatting so days
   * are bucketed identically to the "Sales Report" view (see
   * ReportDateField::query(), which applies the same kind of offset via
   * QueryPluginBase::setFieldTimezoneOffset()).
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
   * Groups daily totals by ISO week ('o-W') or calendar month ('Y-m').
   *
   * Returns keyed arrays with 'total', 'days', and 'avg'.
   */
  protected function aggregateByPeriod(array $daily_data, string $period, \DateTimeZone $timezone): array {
    $aggregated = [];
    foreach ($daily_data as $date => $amount) {
      $day = new \DateTime($date, $timezone);
      $key = $period === 'week' ? $day->format('o-W') : $day->format('Y-m');
      $aggregated[$key]['total'] = ($aggregated[$key]['total'] ?? 0.0) + $amount;
      $aggregated[$key]['days'] = ($aggregated[$key]['days'] ?? 0) + 1;
    }
    foreach ($aggregated as &$data) {
      $data['avg'] = $data['days'] > 0 ? $data['total'] / $data['days'] : 0.0;
    }
    return $aggregated;
  }

  /**
   * Builds the full data payload passed to drupalSettings for Chart.js.
   *
   * Weekly/monthly totals are included in a parallel lookup array for use
   * in hover tooltips only — they are not rendered as chart datasets.
   */
  protected function buildChartData(
    array $date_series,
    array $daily_data,
    array $weekly,
    array $monthly,
    string $currency,
    \DateTimeZone $timezone,
  ): array {
    $daily_values = [];
    $weekly_avg_values = [];
    $monthly_avg_values = [];
    // Tooltip-only lookups — parallel to $date_series by index.
    $weekly_total_values = [];
    $monthly_total_values = [];
    // Labels for which week / month period each day belongs to.
    $weekly_period_labels = [];
    $monthly_period_labels = [];

    $week_boundary_indices = [];
    $month_boundary_indices = [];
    $prev_week = NULL;
    $prev_month = NULL;

    foreach ($date_series as $i => $date) {
      $day = new \DateTime($date, $timezone);
      $week_key = $day->format('o-W');
      $month_key = $day->format('Y-m');

      $daily_values[] = round($daily_data[$date], 2);
      $weekly_avg_values[] = round($weekly[$week_key]['avg'] ?? 0.0, 2);
      $monthly_avg_values[] = round($monthly[$month_key]['avg'] ?? 0.0, 2);
      $weekly_total_values[] = round($weekly[$week_key]['total'] ?? 0.0, 2);
      $monthly_total_values[] = round($monthly[$month_key]['total'] ?? 0.0, 2);
      $weekly_period_labels[] = 'Week ' . $week_key;
      $monthly_period_labels[] = $day->format('F Y');

      if ($prev_week !== NULL && $week_key !== $prev_week) {
        $week_boundary_indices[] = $i;
      }
      if ($prev_month !== NULL && $month_key !== $prev_month) {
        $month_boundary_indices[] = $i;
      }

      $prev_week = $week_key;
      $prev_month = $month_key;
    }

    return [
      'labels' => $date_series,
      'currency' => $currency,
      // Chart datasets (plotted).
      'dailyValues' => $daily_values,
      'weeklyAvgValues' => $weekly_avg_values,
      'monthlyAvgValues' => $monthly_avg_values,
      // Tooltip-only (not plotted as lines).
      'weeklyTotalValues' => $weekly_total_values,
      'monthlyTotalValues' => $monthly_total_values,
      'weeklyPeriodLabels' => $weekly_period_labels,
      'monthlyPeriodLabels' => $monthly_period_labels,
      // Boundary x-indices for annotation lines.
      'weekBoundaries' => $week_boundary_indices,
      'monthBoundaries' => $month_boundary_indices,
    ];
  }

}
