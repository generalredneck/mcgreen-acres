<?php

namespace Drupal\mcgreen_square_sync\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\CurrencyFormatter;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mcgreen_square_sync\DecisionStorage;
use Drupal\mcgreen_square_sync\ItemResolver;
use Drupal\mcgreen_square_sync\SaleFetcher;
use Drupal\mcgreen_square_sync\SalesReport;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One Square sale: link it to an order, or say what its custom amounts are.
 */
class SaleForm extends FormBase {

  use SaleDisplayTrait;

  public function __construct(
    protected SaleFetcher $fetcher,
    protected SalesReport $report,
    protected DecisionStorage $decisions,
    protected ItemResolver $resolver,
    protected EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('date.formatter'),
      $container->get('commerce_price.currency_formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcgreen_square_sync_sale';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $sale_id = ''): array {
    $sale = $this->report->build($this->fetcher->getStartDate())['sales'][$sale_id] ?? NULL;
    if (!$sale) {
      throw new NotFoundHttpException();
    }
    $form_state->set('sale_id', $sale_id);
    $form['#cache'] = ['max-age' => 0];

    $form['details'] = [
      '#type' => 'table',
      '#header' => [$this->t('Sold'), $this->t('Total'), $this->t('Paid by'), $this->t('Square ID')],
      '#rows' => [
        [
          $this->dateFormatter->format($sale['sold_at'], 'custom', 'D, M j Y, g:i a'),
          $this->money($sale['total'], $sale['currency']),
          $this->paidBy($sale),
          $sale_id,
        ],
      ],
    ];

    $form['order'] = $this->buildOrderSection($sale);
    $form['items'] = $this->buildItemsSection($sale);

    $form['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to Square sales'),
      '#url' => Url::fromRoute('mcgreen_square_sync.sales'),
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
   * Requires an order number that names an order which can record a sale.
   */
  public function validateLink(array &$form, FormStateInterface $form_state): void {
    $number = trim((string) $form_state->getValue('order_number'));
    $order = $number === '' ? NULL : $this->findOrder($number);
    if (!$order) {
      $form_state->setErrorByName('order_number', $this->t('There is no placed, uncanceled order numbered "@number".', ['@number' => $number]));
      return;
    }
    $form_state->set('order_id', (int) $order->id());
  }

  /**
   * Links this sale to the entered order.
   */
  public function submitLink(array &$form, FormStateInterface $form_state): void {
    $this->decisions->link([$form_state->get('sale_id') => $form_state->get('order_id')]);
    $this->messenger()->addStatus($this->t('Linked the sale to order @number.', [
      '@number' => trim((string) $form_state->getValue('order_number')),
    ]));
  }

  /**
   * Unlinks this sale.
   */
  public function submitUnlink(array &$form, FormStateInterface $form_state): void {
    $this->decisions->unlink([$form_state->get('sale_id')]);
    $this->messenger()->addStatus($this->t('Unlinked the sale.'));
  }

  /**
   * Requires a quantity for lines mapped to a product.
   */
  public function validateMapping(array &$form, FormStateInterface $form_state): void {
    foreach ($form_state->getValue('lines') ?: [] as $uid => $line) {
      if (is_numeric($line['target'] ?? NULL) && (float) $line['quantity'] <= 0) {
        $form_state->setErrorByName("lines][$uid][quantity", $this->t('Enter a quantity greater than zero.'));
      }
    }
  }

  /**
   * Saves what each custom amount stands for.
   */
  public function submitMapping(array &$form, FormStateInterface $form_state): void {
    $sale_id = $form_state->get('sale_id');
    foreach ($form_state->getValue('lines') ?: [] as $uid => $line) {
      $target = (string) ($line['target'] ?? '');
      if (is_numeric($target)) {
        $this->decisions->setLineMap($sale_id, $uid, DecisionStorage::PRODUCT, (int) $target, (string) $line['quantity']);
      }
      else {
        $type = $target === DecisionStorage::ADJUSTMENT ? DecisionStorage::ADJUSTMENT : NULL;
        $this->decisions->setLineMap($sale_id, $uid, $type);
      }
    }
    $this->messenger()->addStatus($this->t('Saved the item mapping.'));
  }

  /**
   * Shows the order covering the sale, or lets one be chosen.
   */
  protected function buildOrderSection(array $sale): array {
    $section = [
      '#type' => 'details',
      '#title' => $this->t('Drupal order'),
      '#open' => TRUE,
    ];

    if ($sale['link']) {
      $section['current'] = [
        '#type' => 'container',
        'label' => ['#plain_text' => $this->t('Linked to') . ' '],
        'order' => $this->orderLink($sale['link']['order']),
      ];
      if ($mismatch = $this->totalMismatch($sale['link'], $sale['currency'])) {
        $section['warning'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $mismatch];
      }
      $section['unlink'] = [
        '#type' => 'submit',
        '#value' => $this->t('Unlink'),
        '#submit' => ['::submitUnlink'],
        '#limit_validation_errors' => [],
      ];
      return $section;
    }

    $suggested = $sale['match']['order'] ?? NULL;
    if ($sale['match']) {
      $section['suggestion'] = [
        '#type' => 'container',
        'label' => ['#plain_text' => $this->t('Suggested:') . ' '],
        'order' => $this->orderLink($suggested),
        'notes' => ['#plain_text' => ' (' . $this->suggestionNotes($sale) . ')'],
      ];
    }
    $section['order_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Order number'),
      '#description' => $this->t('Links this sale only. Several sales can be linked to the same order.'),
      '#default_value' => $suggested?->getOrderNumber(),
      '#size' => 12,
    ];
    $section['link'] = [
      '#type' => 'submit',
      '#value' => $this->t('Link to order'),
      '#button_type' => 'primary',
      '#validate' => ['::validateLink'],
      '#submit' => ['::submitLink'],
      '#limit_validation_errors' => [['order_number']],
    ];
    return $section;
  }

  /**
   * Lists the sale's items, with a mapping choice for each custom amount.
   */
  protected function buildItemsSection(array $sale): array {
    $section = [
      '#type' => 'details',
      '#title' => $this->t('Items'),
      '#open' => TRUE,
    ];

    if ($sale['link']) {
      $section['list'] = [
        '#theme' => 'item_list',
        '#items' => array_map(fn(array $item) => $this->itemLabel($item, $sale['currency']), $sale['items']),
      ];
      $section['note'] = [
        '#markup' => '<p>' . $this->t('The linked order already records these items, so there is nothing to map.') . '</p>',
      ];
      return $section;
    }

    $section['help'] = [
      '#markup' => '<p>' . $this->t('What each line will be recorded as when the sale is imported; nothing is created yet. Catalog items are mapped once, on the <a href=":url">Items tab</a>. Custom amounts are mapped here.', [
        ':url' => Url::fromRoute('mcgreen_square_sync.items')->toString(),
      ]) . '</p>',
    ];
    $section['lines'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [$this->t('Item'), $this->t('Record as'), $this->t('Quantity (custom amounts)')],
    ];
    $item_maps = $this->decisions->getItemMaps();
    $options = NULL;
    foreach ($sale['items'] as $index => $item) {
      $label = ['#plain_text' => $this->itemLabel($item, $sale['currency'])];
      $uid = $item['uid'] ?? '';
      if (!empty($item['catalog_object_id']) || $uid === '') {
        $section['lines']['catalog_' . $index] = [
          'item' => $label,
          'target' => ['#plain_text' => $this->resolutionText($this->resolver->resolve($item, $sale['maps'], $item_maps), $item)],
          'quantity' => ['#markup' => ''],
        ];
        continue;
      }
      $options ??= [DecisionStorage::ADJUSTMENT => $this->t('Adjustment (not a product)')] + $this->variationOptions();
      $map = $sale['maps'][$uid] ?? NULL;
      $default = '';
      if ($map) {
        $default = $map['type'] === DecisionStorage::PRODUCT ? $map['variation_id'] : DecisionStorage::ADJUSTMENT;
      }
      $section['lines'][$uid] = [
        'item' => $label,
        'target' => [
          '#type' => 'select',
          '#title' => $this->t('Record as'),
          '#title_display' => 'invisible',
          '#options' => $options,
          '#empty_option' => $this->t('- Not mapped -'),
          '#empty_value' => '',
          '#default_value' => $default,
        ],
        'quantity' => [
          '#type' => 'number',
          '#title' => $this->t('Quantity'),
          '#title_display' => 'invisible',
          '#min' => 0,
          '#step' => 'any',
          '#default_value' => $map['quantity'] ?? $item['quantity'],
        ],
      ];
    }
    $section['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save item mapping'),
      '#validate' => ['::validateMapping'],
      '#submit' => ['::submitMapping'],
      '#limit_validation_errors' => [['lines']],
      // Only custom amounts have anything to save here.
      '#access' => $options !== NULL,
    ];
    return $section;
  }

  /**
   * Finds a placed order that has not been canceled, by its order number.
   */
  protected function findOrder(string $number): ?OrderInterface {
    $storage = $this->entityTypeManager->getStorage('commerce_order');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('order_number', $number)
      ->condition('state', ['draft', 'canceled'], 'NOT IN')
      ->range(0, 1)
      ->execute();
    /** @var \Drupal\commerce_order\Entity\OrderInterface|null $order */
    $order = $ids ? $storage->load(reset($ids)) : NULL;
    return $order;
  }

}
