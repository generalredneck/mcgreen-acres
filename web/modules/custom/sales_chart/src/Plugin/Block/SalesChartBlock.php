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
    // Vary the cached block output whenever date[min] or date[max] changes.
    return array_merge(parent::getCacheContexts(), ['url.query_args:date']);
  }

  public function build(): array {
    $config = $this->getConfiguration();
    $days = (int) $config['days'];
    $currency = $config['currency_code'];

    // Honour date[min] / date[max] query params (same identifiers used by the
    // Views sales report exposed date filter) when present, otherwise fall back
    // to the configured rolling-days window.
    $date_params = $this->requestStack->getCurrentRequest()->query->all('date');
    $min_raw = $date_params['min'] ?? NULL;
    $max_raw = $date_params['max'] ?? NULL;

    try {
      $start = $min_raw ? new \DateTime($min_raw) : NULL;
      $end   = $max_raw ? new \DateTime($max_raw) : NULL;
    }
    catch (\Exception) {
      $start = $end = NULL;
    }

    if (!$start || !$end || $start > $end) {
      $end   = new \DateTime('today');
      $start = (clone $end)->modify("-{$days} days");
    }

    $daily_raw = $this->queryDailyTotals($start, $end, $currency);
    $date_series = $this->generateDateSeries($start, $end);

    // Fill every day in the range; missing days become 0.
    $daily_data = [];
    foreach ($date_series as $date) {
      $daily_data[$date] = $daily_raw[$date] ?? 0.0;
    }

    $weekly = $this->aggregateByPeriod($daily_data, 'week');
    $monthly = $this->aggregateByPeriod($daily_data, 'month');
    $chart_data = $this->buildChartData($date_series, $daily_data, $weekly, $monthly, $currency);

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
   */
  protected function queryDailyTotals(\DateTime $start, \DateTime $end, string $currency): array {
    $end_exclusive = (clone $end)->modify('+1 day');

    $results = $this->database->query(
      "SELECT DATE_FORMAT(FROM_UNIXTIME(r.created), '%Y-%m-%d') AS day,
              SUM(a.amount_number) AS total
       FROM {commerce_order_report} r
       INNER JOIN {commerce_order_report__amount} a
         ON a.entity_id = r.report_id AND a.deleted = 0
       WHERE r.type = 'order_report'
         AND a.amount_currency_code = :currency
         AND r.created >= :start
         AND r.created < :end
       GROUP BY day
       ORDER BY day ASC",
      [
        ':currency' => $currency,
        ':start' => $start->getTimestamp(),
        ':end' => $end_exclusive->getTimestamp(),
      ]
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
  protected function aggregateByPeriod(array $daily_data, string $period): array {
    $aggregated = [];
    foreach ($daily_data as $date => $amount) {
      $ts = strtotime($date);
      $key = $period === 'week' ? date('o-W', $ts) : date('Y-m', $ts);
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
      $ts = strtotime($date);
      $week_key = date('o-W', $ts);
      $month_key = date('Y-m', $ts);

      $daily_values[] = round($daily_data[$date], 2);
      $weekly_avg_values[] = round($weekly[$week_key]['avg'] ?? 0.0, 2);
      $monthly_avg_values[] = round($monthly[$month_key]['avg'] ?? 0.0, 2);
      $weekly_total_values[] = round($weekly[$week_key]['total'] ?? 0.0, 2);
      $monthly_total_values[] = round($monthly[$month_key]['total'] ?? 0.0, 2);
      $weekly_period_labels[] = 'Week ' . $week_key;
      $monthly_period_labels[] = date('F Y', $ts);

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
