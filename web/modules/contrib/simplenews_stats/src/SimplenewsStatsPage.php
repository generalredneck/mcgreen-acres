<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\simplenews\Entity\Newsletter;

/**
 * Simplenews statistics page class.
 */
class SimplenewsStatsPage {

  use StringTranslationTrait;

  /**
   * The entity SimpleNews.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * The entity Newsletter from simplenews.
   *
   * @var \Drupal\simplenews\Entity\Newsletter
   */
  protected Newsletter $simplenews;

  /**
   * The simplenews values.
   *
   * @var array
   */
  protected array $simplenewsValues;

  /**
   * All dates.
   *
   * @var array
   */
  protected array $dates;

  /**
   * Series.
   *
   * @var array
   */
  protected array $series;

  /**
   * Number of clicks.
   *
   * @var int|null
   * @see queryCount()
   */
  protected ?int $countClick = NULL;

  /**
   * Number of views.
   *
   * @var int|null
   * @see queryCount()
   */
  protected ?int $countView = NULL;

  /**
   * SimplenewsStatsPage Constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity used as simplenews.
   */
  public function __construct(EntityInterface|null $entity) {
    if (!$entity instanceof EntityInterface) {
      $this->entity = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->create([
          'title' => $this->t('Deleted'),
          'nid' => 0,
          'type' => 'deleted',
        ]);

      return;
    }

    $this->entity = $entity;
    if (!$this->entity->get('simplenews_issue')->isEmpty()) {
      $this->simplenewsValues = $this->entity->get('simplenews_issue')->first()
        ->getValue();

      $this->simplenews = $this->entity->get('simplenews_issue')->entity;
    }
  }

  /**
   * Return the total of Clicks.
   *
   * @return int
   *   The number of clicks.
   */
  public function getCountClicks(): int {
    return $this->queryCount('click');
  }

  /**
   * Return the total of Views.
   *
   * @return int
   *   The number of views.
   */
  public function getCountViews(): int {
    return $this->queryCount('view');
  }

  /**
   * Return the total of Views.
   *
   * @return int
   *   The number of email sent.
   */
  public function getCountTotalMails(): int {
    $simplenews_stats = $this->getSimplenewsStats();
    return ($simplenews_stats) ? $simplenews_stats->getTotalMails() : 0;
  }

  /**
   * Return the detail of Clicks.
   *
   * @return array
   *   The detail of clicks formatted for chart.
   */
  public function getDetailClicks(): array {
    return [
      'label' => $this->t('Clicks'),
      'backgroundColor' => '#4bc0c0',
      'borderColor' => '#4bc0c0',
      'fill' => FALSE,
      'data' => $this->queryDetail('click'),
    ];
  }

  /**
   * Return the detail of view actions.
   *
   * @return array
   *   The detail of views formatted for chart.
   */
  public function getDetailViews(): array {
    return [
      'label' => $this->t('Views'),
      'backgroundColor' => '#96f',
      'borderColor' => '#96f',
      'fill' => FALSE,
      'data' => $this->queryDetail('view'),
    ];
  }

  /**
   * Calculation of percent.
   *
   * @param int $number
   *   The number to compare with total mail sent.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The percent.
   */
  public function getPercent(int $number): TranslatableMarkup {
    if ($number) {
      $percent = number_format(($number / $this->getCountTotalMails()) * 100, 2);
    }
    else {
      $percent = 0;
    }
    return $this->t('@percent %', ['@percent' => $percent]);
  }

  /**
   * Return the most clicked links.
   *
   * @return array
   *   List of most clicked links.
   */
  public function getTopLinks(): array {
    $links = [];

    $query = Database::getConnection()
      ->select('simplenews_stats_item', 'ss');

    $query->fields('ss', ['route_path']);
    $query->addExpression('COUNT(ssiid)', 'number');
    $query->condition('entity_type', $this->entity->getEntityTypeId())
      ->condition('entity_id', $this->entity->id())
      ->condition('title', 'click')
      ->groupBy('route_path')
      ->orderBy('number', 'DESC');

    $results = $query->execute();

    foreach ($results as $data) {
      $links[] = ['route_path' => $data->route_path, 'count' => $data->number];
    }

    return $links;
  }

  /**
   * Return the most used devices.
   *
   * @return array
   *   List of most used devices.
   */
  public function getTopDevices() {
    $devices = [];

    $query = Database::getConnection()
      ->select('simplenews_stats_item', 'ss');

    $query->fields('ss', ['device']);
    $query->addExpression('COUNT(ssiid)', 'number');
    $query->condition('entity_type', $this->entity->getEntityTypeId())
      ->condition('entity_id', $this->entity->id())
      ->condition('title', 'view')
      ->groupBy('device')
      ->orderBy('number', 'DESC');

    $results = $query->execute();

    foreach ($results as $data) {
      $devices[] = ['device' => $data->device, 'count' => $data->number];
    }

    return $devices;
  }

  /**
   * The statistics page.
   *
   * @return array
   *   Array renderable of the page.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getPage(): array {
    $content = [];
    $content['report'] = [
      '#theme' => 'table',
      '#header' => [
        $this->t('Title'),
        $this->t('Sent status'),
        $this->t('Views'),
        $this->t('Clicks'),
        $this->t('Total emails sent'),
        $this->t('% Views'),
        $this->t('% Clicks'),
        $this->t('Detail'),
      ],
      '#rows' => [
        [
          $this->entity->toLink(),
          // @todo Add status description.
          $this->simplenewsValues['status'],
          $this->getCountViews(),
          $this->getCountClicks(),
          $this->getCountTotalMails(),
          $this->getPercent($this->getCountViews()),
          $this->getPercent($this->getCountClicks()),
          $this->getLinkDetail(),
        ],
      ],
    ];

    $chart_line_id = Html::getUniqueId('chart_line');
    $content['chart_line'] = [
      '#type' => 'html_tag',
      '#tag' => 'canvas',
      '#attributes' => [
        'id' => $chart_line_id,
      ],
      '#attached' => [
        'library' => ['simplenews_stats/simplenews_stats.drupal_chartjs'],
        'drupalSettings' => [
          'simplenews_stats' => [
            $chart_line_id => [
              'labels' => $this->getDatesForCharts(),
              'datasets' => $this->getSeriesForCharts(),
              'type' => 'line',
            ],
          ],
        ],
      ],
    ];

    $content['paths'] = [
      '#prefix' => '<h2>' . $this->t('Top links') . '</h2>',
      '#theme' => 'table',
      '#header' => [$this->t('Path'), $this->t('Count')],
      '#rows' => $this->getTopLinks(),
    ];

    $content['devices'] = [
      '#prefix' => '<h2>' . $this->t('Devices') . '</h2>',
      '#theme' => 'table',
      '#header' => [$this->t('Device type'), $this->t('Count')],
      '#rows'   => $this->getTopDevices(),
    ];

    return $content;
  }

  /**
   * Helper function for query detail.
   *
   * @param string $type
   *   The type of statistics (click,view)
   *
   * @return array
   *   The stats detail.
   */
  protected function queryDetail(string $type): array {
    $query = Database::getConnection()
      ->select('simplenews_stats_item', 'ss');

    $query->addExpression('COUNT(ssiid)', 'number');
    $query->addExpression("FROM_UNIXTIME(created,'%Y-%m-%d')", 'day');
    $query->condition('title', $type)
      ->condition('entity_type', $this->entity->getEntityTypeId())
      ->condition('entity_id', $this->entity->id())
      ->groupBy('day');

    $results = $query->execute();

    $data = [];
    foreach ($results as $result) {
      $data[$result->day] = (int) $result->number;
    }

    return $data;
  }

  /**
   * Helper function for count query.
   *
   * @param string $type
   *   The type of statistics (click,view)
   *
   * @return int
   *   The count.
   */
  protected function queryCount(string $type): int {
    $stored = &$this->{'count' . ucfirst($type)};
    if ($stored !== NULL) {
      return $stored;
    }

    $query = \Drupal::entityQuery('simplenews_stats_item')
      ->condition('entity_type', $this->entity->getEntityTypeId())
      ->condition('entity_id', $this->entity->id())
      ->condition('title', $type)
      ->accessCheck();

    // Affect new value before return it.
    $stored = (int) $query->count()->execute();

    return $stored;
  }

  /**
   * Return an array of dates.
   *
   * @return array
   *   Array of dates.
   */
  protected function getDates(): array {
    if (!empty($this->dates)) {
      return $this->dates;
    }

    $dates = [];
    foreach ($this->getSeries() as $data) {
      $dates += $data['data'];
    }

    if (empty($dates)) {
      // Returns early when there is no dataset.
      return [];
    }

    // Sort on keys(dates).
    ksort($dates);

    // Get first key(date).
    reset($dates);
    $start = key($dates);

    // Get last key(date).
    end($dates);
    $end = key($dates);

    $period = new \DatePeriod(new \DateTime($start), new \DateInterval('P1D'), new \DateTime($end . ' + 1 day'));

    $range = [];
    foreach ($period as $date) {
      $formatted_date = $date->format('Y-m-d');
      $range[$formatted_date] = $formatted_date;
    }

    $this->dates = $range;
    return $this->dates;
  }

  /**
   * Prepare dates for Charts module.
   *
   * @return array
   *   Array of dates.
   */
  protected function getDatesForCharts(): array {
    return array_values($this->getDates());
  }

  /**
   * Return all series data.
   *
   * @return array
   *   The series.
   */
  protected function getSeries(): array {
    if (empty($this->series)) {
      $this->series[] = $this->getDetailClicks();
      $this->series[] = $this->getDetailViews();
    }
    return $this->series;
  }

  /**
   * Prepare series for Charts module.
   *
   * @return array
   *   The series for charts.
   */
  protected function getSeriesForCharts(): array {
    $series = $this->getSeries();
    foreach ($series as &$item) {
      $data = [];
      foreach ($this->getDates() as $raw_date => $date) {
        $data[] = !empty($item['data'][$raw_date]) ? $item['data'][$raw_date] : 0;
      }
      $item['data'] = $data;
    }

    return $series;
  }

  /**
   * Return the simplenews stats entity in relation to the entity.
   *
   * @return \Drupal\simplenews_stats\SimplenewsStatsInterface|null
   *   The simplenews stats entity.
   */
  protected function getSimplenewsStats(): ?SimplenewsStatsInterface {
    return \Drupal::entityTypeManager()
      ->getStorage('simplenews_stats')
      ->getFromRelatedEntity($this->entity);
  }

  /**
   * Return the detail link.
   *
   * @return \Drupal\Core\Link
   *   The detail link.
   */
  protected function getLinkDetail(): Link {
    /** @var \Drupal\simplenews_stats\SimplenewsStatsTools $simplenewsStatsTools */
    $simplenewsStatsTools = \Drupal::service('simplenews_stats.tools');

    $url = Url::fromRoute('entity.simplenews_stats_item.collection', [
      'entity' => $simplenewsStatsTools->getEntityLabel($this->entity, TRUE),
    ]);
    $url->setOption('attributes', ['class' => ['button']]);
    return Link::fromTextAndUrl($this->t('Detail'), $url);
  }

}
