<?php

namespace Drupal\mcgreen_square_sync\Form;

use Drupal\commerce_price\CurrencyFormatter;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\mcgreen_square_sync\DecisionStorage;
use Drupal\mcgreen_square_sync\ItemResolver;
use Drupal\mcgreen_square_sync\SaleFetcher;
use Drupal\mcgreen_square_sync\SalesReport;
use Drupal\mcgreen_square_sync\SquareException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists Square sales next to the Drupal orders that record them.
 */
class SalesForm extends FormBase {

  use SaleDisplayTrait;

  public function __construct(
    protected SaleFetcher $fetcher,
    protected SalesReport $report,
    protected DecisionStorage $decisions,
    protected ItemResolver $resolver,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StateInterface $state,
    protected DateFormatterInterface $dateFormatter,
    protected CurrencyFormatter $currencyFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mcgreen_square_sync.fetcher'),
      $container->get('mcgreen_square_sync.report'),
      $container->get('mcgreen_square_sync.decisions'),
      $container->get('mcgreen_square_sync.resolver'),
      $container->get('entity_type.manager'),
      $container->get('state'),
      $container->get('date.formatter'),
      $container->get('commerce_price.currency_formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcgreen_square_sync_sales';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $start = $this->fetcher->getStartDate();
    $report = $this->report->build($start);
    $show = (string) $this->getRequest()->query->get('show');
    $labels = $this->statusLabels();
    if (!isset($labels[$show])) {
      $show = '';
    }

    $form['#cache'] = ['max-age' => 0];

    $last_fetch = $this->state->get(SaleFetcher::LAST_FETCH_STATE);
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Sales since @start. Last fetched: @fetched. Linking records which order covers a sale; it does not change the order.', [
        '@start' => $this->dateFormatter->format($start->getTimestamp(), 'custom', 'F j, Y'),
        '@fetched' => $last_fetch ? $this->dateFormatter->format($last_fetch, 'short') : $this->t('never'),
      ]) . '</p>',
    ];
    // Not in an "actions" group, which the admin theme moves below the tables.
    $form['fetch'] = [
      '#type' => 'submit',
      '#value' => $this->t('Fetch from Square'),
      '#submit' => ['::submitFetch'],
    ];

    $form['summary'] = $this->buildSummary($report);

    $filters = [];
    foreach (['' => $this->t('All')] + $labels as $status => $label) {
      $filters[] = [
        '#type' => 'link',
        '#title' => $label,
        '#url' => Url::fromRoute('mcgreen_square_sync.sales', [], ['query' => $status ? ['show' => $status] : []]),
        '#attributes' => $status === $show ? ['class' => ['is-active'], 'aria-current' => 'true'] : [],
      ];
      $filters[] = ['#markup' => ' &nbsp; '];
    }
    $form['filters'] = [
      '#type' => 'container',
      'label' => ['#markup' => '<strong>' . $this->t('Show:') . '</strong> '],
      'links' => $filters,
    ];

    $form['link'] = [
      '#type' => 'submit',
      '#value' => $this->t('Link selected'),
      '#button_type' => 'primary',
      '#submit' => ['::submitLink'],
    ];
    $form['unlink'] = [
      '#type' => 'submit',
      '#value' => $this->t('Unlink selected'),
      '#submit' => ['::submitUnlink'],
    ];

    $item_maps = $this->decisions->getItemMaps();
    $options = [];
    foreach ($report['sales'] as $sale_id => $sale) {
      if ($show && $sale['status'] !== $show) {
        continue;
      }
      // The key of a suggested row names the order shown, so that exactly what
      // was seen is what gets linked.
      $key = $sale['status'] === SalesReport::MATCHED ? $sale_id . ':' . $sale['match']['order']->id() : $sale_id;
      $items = [];
      foreach ($sale['items'] as $item) {
        $label = $this->itemLabel($item, $sale['currency']);
        // A linked sale's order already records its items.
        if (!$sale['link']) {
          $label .= ' → ' . $this->resolutionText($this->resolver->resolve($item, $sale['maps'], $item_maps), $item);
        }
        $items[] = $label;
      }
      $options[$key] = [
        'sold' => $this->dateFormatter->format($sale['sold_at'], 'custom', 'D, M j Y, g:i a'),
        'total' => $this->money($sale['total'], $sale['currency']),
        'paid' => $this->paidBy($sale),
        'items' => ['data' => ['#theme' => 'item_list', '#items' => $items]],
        'order' => ['data' => $this->orderCell($sale)],
        'details' => [
          'data' => Link::createFromRoute($this->t('Open'), 'mcgreen_square_sync.sale', ['sale_id' => $sale_id])->toRenderable(),
        ],
        // Only these rows have something the buttons can do.
        '#disabled' => !in_array($sale['status'], [SalesReport::MATCHED, SalesReport::LINKED], TRUE),
      ];
    }
    $form['sales'] = [
      '#type' => 'tableselect',
      '#caption' => $this->t('Square sales'),
      '#header' => [
        'sold' => $this->t('Sold'),
        'total' => $this->t('Total'),
        'paid' => $this->t('Paid by'),
        'items' => $this->t('Items'),
        'order' => $this->t('Drupal order'),
        'details' => $this->t('Sale'),
      ],
      '#options' => $options,
      '#empty' => $last_fetch ? $this->t('No sales to show.') : $this->t('Select "Fetch from Square" to load sales.'),
    ];

    $unmatched = [];
    foreach ($report['unmatched_payments'] as $payment) {
      $unmatched[] = [
        ['data' => $this->orderLink($payment['order'])],
        $this->dateFormatter->format($payment['time'], 'custom', 'D, M j Y, g:i a'),
        $this->money($payment['amount'], 'USD'),
      ];
    }
    $form['unmatched'] = [
      '#type' => 'table',
      '#caption' => $this->t('Drupal "square" payments with no matching Square sale'),
      '#header' => [$this->t('Order'), $this->t('Placed'), $this->t('Amount')],
      '#rows' => $unmatched,
      '#empty' => $this->t('Every Drupal "square" payment is accounted for.'),
      '#access' => (bool) $last_fetch,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Each button has its own submit handler.
  }

  /**
   * Fetches the sales from Square.
   */
  public function submitFetch(array &$form, FormStateInterface $form_state): void {
    try {
      $count = $this->fetcher->fetch();
      $this->messenger()->addStatus($this->formatPlural($count, 'Fetched 1 sale from Square.', 'Fetched @count sales from Square.'));
    }
    catch (SquareException $e) {
      $this->logger('mcgreen_square_sync')->error('@message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($e->getMessage());
    }
  }

  /**
   * Links the selected sales to their suggested orders.
   */
  public function submitLink(array &$form, FormStateInterface $form_state): void {
    $report = $this->report->build($this->fetcher->getStartDate());
    $links = $this->report->getSuggestedLinks($report, $this->selected($form_state));
    if (!$links) {
      $this->messenger()->addWarning($this->t('Nothing was linked. Select sales that have a suggested order; link others from their sale page.'));
      return;
    }
    $this->decisions->link($links);
    $this->messenger()->addStatus($this->t('Sales linked: @sales. Orders: @orders.', [
      '@sales' => count($links),
      '@orders' => count(array_unique($links)),
    ]));
  }

  /**
   * Unlinks the selected sales.
   */
  public function submitUnlink(array &$form, FormStateInterface $form_state): void {
    $linked = array_intersect($this->selected($form_state), array_keys($this->decisions->getLinks()));
    if (!$linked) {
      $this->messenger()->addWarning($this->t('Nothing was unlinked. Select sales that are linked.'));
      return;
    }
    $this->decisions->unlink($linked);
    $this->messenger()->addStatus($this->formatPlural(count($linked), 'Unlinked 1 sale.', 'Unlinked @count sales.'));
  }

  /**
   * The keys of the ticked rows.
   *
   * @return string[]
   *   Row keys.
   */
  protected function selected(FormStateInterface $form_state): array {
    return array_map('strval', array_values(array_filter($form_state->getValue('sales') ?: [])));
  }

  /**
   * Builds the table of counts and amounts per status.
   */
  protected function buildSummary(array $report): array {
    $totals = [];
    $mismatched = [];
    foreach ($report['sales'] as $sale) {
      $totals[$sale['status']] ??= [0, 0];
      $totals[$sale['status']][0]++;
      $totals[$sale['status']][1] += $sale['total'];
      if ($sale['link'] && $this->totalMismatch($sale['link'], $sale['currency'])) {
        $mismatched[$sale['link']['order']->id()] = $sale['link']['order_total'];
      }
    }

    $rows = [];
    foreach ($this->statusLabels() as $status => $label) {
      [$count, $amount] = $totals[$status] ?? [0, 0];
      $rows[] = [$label, $count, $this->money($amount, 'USD')];
    }
    $rows[] = [
      $this->t('Drupal "square" payments with no Square sale'),
      count($report['unmatched_payments']),
      $this->money(array_sum(array_column($report['unmatched_payments'], 'amount')), 'USD'),
    ];
    $rows[] = [
      $this->t('Linked orders whose total differs from their sales'),
      count($mismatched),
      $this->money(array_sum($mismatched), 'USD'),
    ];

    return [
      '#type' => 'table',
      '#header' => [$this->t('Summary'), $this->t('Count'), $this->t('Amount')],
      '#rows' => $rows,
    ];
  }

  /**
   * Builds the Drupal order cell for a sale.
   */
  protected function orderCell(array $sale): array {
    if ($sale['link']) {
      $mismatch = $this->totalMismatch($sale['link'], $sale['currency']);
      return [
        'order' => $this->orderLink($sale['link']['order']),
        'notes' => ['#plain_text' => ' (' . $this->t('linked') . ')'],
        'warning' => $mismatch ? ['#type' => 'html_tag', '#tag' => 'div', '#value' => $mismatch] : [],
      ];
    }
    if ($sale['match']) {
      return [
        'label' => ['#plain_text' => $this->t('Suggested:') . ' '],
        'order' => $this->orderLink($sale['match']['order']),
        'notes' => ['#plain_text' => ' (' . $this->suggestionNotes($sale) . ')'],
      ];
    }
    return [
      '#plain_text' => $sale['status'] === SalesReport::MISSING ? $this->t('Not found') : $this->t('Not linked'),
    ];
  }

}
