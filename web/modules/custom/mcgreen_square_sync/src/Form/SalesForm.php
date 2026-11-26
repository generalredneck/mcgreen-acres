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
use Drupal\mcgreen_square_sync\ImportException;
use Drupal\mcgreen_square_sync\ItemResolver;
use Drupal\mcgreen_square_sync\SaleFetcher;
use Drupal\mcgreen_square_sync\SaleImporter;
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
    protected SaleImporter $importer,
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
      $container->get('mcgreen_square_sync.importer'),
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
    // Importing is confirmed first, because it cannot simply be undone.
    if ($plan = $form_state->get('import')) {
      return $this->buildConfirmation($form, $report, $plan);
    }

    $last_fetch = $this->state->get(SaleFetcher::LAST_FETCH_STATE);
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Sales since @start. Last fetched: @fetched. Linking records which order already covers a sale; importing creates a new order from it.', [
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

    $buttons = [
      'link' => [$this->t('Link selected'), '::submitLink'],
      'import_selected' => [$this->t('Import selected'), '::submitImport'],
      'ignore' => [$this->t('Ignore selected'), '::submitIgnore'],
      'undo' => [$this->t('Unlink or restore selected'), '::submitUndo'],
    ];
    foreach ($buttons as $name => [$label, $handler]) {
      $form[$name] = ['#type' => 'submit', '#value' => $label, '#name' => $name, '#submit' => [$handler]];
    }
    $form['link']['#button_type'] = 'primary';

    $form['sales'] = $this->buildSales($report, $show, (bool) $last_fetch);

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
   * Ignores the selected sales that are not recorded in an order.
   */
  public function submitIgnore(array &$form, FormStateInterface $form_state): void {
    $links = $this->decisions->getLinks();
    $ids = array_filter($this->selectedSaleIds($form_state), fn(string $id) => !isset($links[$id]));
    if (!$ids) {
      $this->messenger()->addWarning($this->t('Nothing was ignored. Select sales that are not linked or imported.'));
      return;
    }
    $this->decisions->ignore($ids);
    $this->messenger()->addStatus($this->formatPlural(count($ids), 'Ignored 1 sale.', 'Ignored @count sales.'));
  }

  /**
   * Unlinks linked sales and stops ignoring ignored ones.
   */
  public function submitUndo(array &$form, FormStateInterface $form_state): void {
    $selected = $this->selectedSaleIds($form_state);
    $linked = array_diff(
      array_intersect($selected, array_keys($this->decisions->getLinks())),
      $this->decisions->getImportedSaleIds(),
    );
    $ignored = array_intersect($selected, array_keys($this->decisions->getIgnored()));
    if (!$linked && !$ignored) {
      $this->messenger()->addWarning($this->t('Nothing was changed. Select sales that are linked or ignored.'));
      return;
    }
    $this->decisions->unlink($linked);
    $this->decisions->restore($ignored);
    $this->messenger()->addStatus($this->t('Sales unlinked: @unlinked. Sales no longer ignored: @restored.', [
      '@unlinked' => count($linked),
      '@restored' => count($ignored),
    ]));
  }

  /**
   * Gathers the selected sales that can be imported, to be confirmed.
   */
  public function submitImport(array &$form, FormStateInterface $form_state): void {
    $report = $this->report->build($this->fetcher->getStartDate());
    $item_maps = $this->decisions->getItemMaps();
    $plan = [];
    $skipped = [];
    foreach ($this->selected($form_state) as $key) {
      $sale_id = explode(':', $key)[0];
      $sale = $report['sales'][$sale_id] ?? NULL;
      if (!$sale) {
        continue;
      }
      $problems = [];
      if ($sale['link'] || $sale['status'] === SalesReport::IGNORED) {
        $problems[] = $this->t('it is already linked, imported or ignored');
      }
      elseif ($sale['match']) {
        $problems[] = $this->t('it may already be order @order, so link it, or import it from its sale page', [
          '@order' => $sale['match']['order']->getOrderNumber(),
        ]);
      }
      else {
        $problems = $this->importer->getProblems($sale, $sale['maps'], $item_maps);
      }
      $gateway = (string) $form_state->getValue(['sales', $key, 'gateway']);
      if (!$problems && $this->importer->needsGatewayChoice($sale) && $gateway === '') {
        $problems[] = $this->t('choose how to record its payment');
      }
      if ($problems) {
        $skipped[] = $this->t('@sale: @problems', [
          '@sale' => $this->saleName($sale),
          '@problems' => implode('; ', $problems),
        ]);
        continue;
      }
      $plan[$sale_id] = $gateway ?: NULL;
    }

    foreach ($skipped as $message) {
      $this->messenger()->addWarning($message);
    }
    if (!$plan) {
      $this->messenger()->addWarning($this->t('Nothing to import.'));
      return;
    }
    $form_state->set('import', $plan);
    $form_state->setRebuild();
  }

  /**
   * Imports the confirmed sales.
   */
  public function submitConfirm(array &$form, FormStateInterface $form_state): void {
    foreach ($form_state->get('import') as $sale_id => $gateway) {
      try {
        $order = $this->importer->import((string) $sale_id, $gateway);
        $this->messenger()->addStatus($this->t('Imported <a href=":url">order @number</a>.', [
          ':url' => $order->toUrl()->toString(),
          '@number' => $order->getOrderNumber(),
        ]));
      }
      catch (ImportException $e) {
        $arguments = ['@sale' => $sale_id, '@message' => $e->getMessage()];
        $this->logger('mcgreen_square_sync')->error('Sale @sale: @message', $arguments);
        $this->messenger()->addError($this->t('Sale @sale: @message', $arguments));
      }
    }
    $this->submitCancel($form, $form_state);
  }

  /**
   * Leaves the confirmation step.
   */
  public function submitCancel(array &$form, FormStateInterface $form_state): void {
    $form_state->set('import', NULL);
    $form_state->setRebuild(FALSE);
  }

  /**
   * The keys of the ticked rows.
   *
   * @return string[]
   *   Row keys.
   */
  protected function selected(FormStateInterface $form_state): array {
    $rows = $form_state->getValue('sales') ?: [];
    return array_map('strval', array_keys(array_filter($rows, fn($row) => !empty($row['select']))));
  }

  /**
   * The sale IDs of the ticked rows.
   *
   * @return string[]
   *   Sale IDs.
   */
  protected function selectedSaleIds(FormStateInterface $form_state): array {
    return array_map(fn(string $key) => explode(':', $key)[0], $this->selected($form_state));
  }

  /**
   * Builds the table of sales, with a checkbox on rows that can be acted on.
   */
  protected function buildSales(array $report, string $show, bool $fetched): array {
    $table = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#caption' => $this->t('Square sales'),
      '#header' => [
        // Core's script puts a "select all" checkbox in this cell.
        ['class' => ['select-all']],
        $this->t('Sold'),
        $this->t('Total'),
        $this->t('Paid by'),
        $this->t('Items'),
        $this->t('Drupal order'),
        $this->t('Import cash as'),
        $this->t('Sale'),
      ],
      '#attached' => ['library' => ['core/drupal.tableselect']],
      '#empty' => $fetched ? $this->t('No sales to show.') : $this->t('Select "Fetch from Square" to load sales.'),
    ];

    $item_maps = $this->decisions->getItemMaps();
    $gateways = $this->gatewayOptions();
    foreach ($report['sales'] as $sale_id => $sale) {
      if ($show && $sale['status'] !== $show) {
        continue;
      }
      // The key of a suggested row names the order shown, so that exactly what
      // was seen is what gets linked.
      $key = $sale['status'] === SalesReport::MATCHED ? $sale_id . ':' . $sale['match']['order']->id() : $sale_id;
      $open = !$sale['link'] && $sale['status'] !== SalesReport::IGNORED;
      $items = [];
      foreach ($sale['items'] as $item) {
        $label = $this->itemLabel($item, $sale['currency']);
        // A linked sale's order already records its items.
        if ($open) {
          $label .= ' → ' . $this->resolutionText($this->resolver->resolve($item, $sale['maps'], $item_maps), $item);
        }
        $items[] = $label;
      }
      $order = $this->orderCell($sale);
      if ($open) {
        $order['ready'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->importer->getProblems($sale, $sale['maps'], $item_maps) ? $this->t('Not ready to import') : $this->t('Ready to import'),
        ];
      }

      $table[$key] = [
        // An imported order can only be undone by deleting it.
        'select' => $sale['status'] === SalesReport::IMPORTED ? ['#markup' => ''] : [
          '#type' => 'checkbox',
          '#title' => $this->t('Select the sale of @sale', ['@sale' => $this->saleName($sale)]),
          '#title_display' => 'invisible',
        ],
        'sold' => ['#plain_text' => $this->dateFormatter->format($sale['sold_at'], 'custom', 'D, M j Y, g:i a')],
        'total' => ['#plain_text' => $this->money($sale['total'], $sale['currency'])],
        'paid' => ['#plain_text' => $this->paidBy($sale)],
        'items' => ['#theme' => 'item_list', '#items' => $items],
        'order' => $order,
        'gateway' => $open && $this->importer->needsGatewayChoice($sale) ? [
          '#type' => 'select',
          '#title' => $this->t('Record the payment of @sale as', ['@sale' => $this->saleName($sale)]),
          '#title_display' => 'invisible',
          '#options' => $gateways,
          '#empty_option' => $this->t('- Choose -'),
          '#empty_value' => '',
          '#default_value' => $this->defaultGateway($sale),
        ] : ['#markup' => ''],
        'details' => Link::createFromRoute($this->t('Open'), 'mcgreen_square_sync.sale', ['sale_id' => $sale_id])->toRenderable(),
      ];
    }
    return $table;
  }

  /**
   * Shows what is about to be imported.
   */
  protected function buildConfirmation(array $form, array $report, array $plan): array {
    $gateways = $this->gatewayOptions();
    $rows = [];
    $total = 0;
    foreach ($plan as $sale_id => $gateway) {
      $sale = $report['sales'][$sale_id] ?? NULL;
      if (!$sale) {
        continue;
      }
      $total += $sale['total'];
      $as = [];
      if ($sale['card']) {
        $as[] = $this->t('Square @amount', ['@amount' => $this->money($sale['card'], $sale['currency'])]);
      }
      if ($gateway) {
        $as[] = $this->t('@gateway @amount', [
          '@gateway' => $gateways[$gateway] ?? $gateway,
          '@amount' => $this->money($sale['cash'] + $sale['other'], $sale['currency']),
        ]);
      }
      $rows[] = [
        $this->saleName($sale),
        implode(' + ', $as),
        $this->importer->deductsStock($sale) ? $this->t('Deducted') : $this->t('Unchanged'),
      ];
    }
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Each sale becomes a new completed order. No receipt is sent. To undo an import you delete its order, which removes its payments and report entries but does not put stock back.') . '</p>',
    ];
    $form['plan'] = [
      '#type' => 'table',
      '#header' => [$this->t('Sale'), $this->t('Recorded as'), $this->t('Stock')],
      '#rows' => $rows,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['confirm'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import @count sale(s) totaling @total', [
        '@count' => count($rows),
        '@total' => $this->money($total, 'USD'),
      ]),
      '#button_type' => 'primary',
      '#submit' => ['::submitConfirm'],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::submitCancel'],
      '#limit_validation_errors' => [],
    ];
    return $form;
  }

  /**
   * Names a sale by when it happened and what it came to.
   */
  protected function saleName(array $sale): string {
    return $this->dateFormatter->format($sale['sold_at'], 'custom', 'M j Y, g:i a') . ', ' . $this->money($sale['total'], $sale['currency']);
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
    if ($sale['status'] === SalesReport::IGNORED) {
      return [
        '#plain_text' => $sale['ignored_reason']
          ? $this->t('Ignored: @reason', ['@reason' => $sale['ignored_reason']])
          : $this->t('Ignored'),
      ];
    }
    if ($sale['link']) {
      $mismatch = $this->totalMismatch($sale['link'], $sale['currency']);
      return [
        'order' => $this->orderLink($sale['link']['order']),
        'notes' => ['#plain_text' => ' (' . ($sale['status'] === SalesReport::IMPORTED ? $this->t('imported') : $this->t('linked')) . ')'],
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
      'label' => ['#plain_text' => $sale['status'] === SalesReport::MISSING ? $this->t('Not found') : $this->t('Not linked')],
    ];
  }

}
