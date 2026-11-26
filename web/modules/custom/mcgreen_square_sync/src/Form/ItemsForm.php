<?php

namespace Drupal\mcgreen_square_sync\Form;

use Drupal\commerce_price\CurrencyFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mcgreen_square_sync\DecisionStorage;
use Drupal\mcgreen_square_sync\ItemResolver;
use Drupal\mcgreen_square_sync\SaleFetcher;
use Drupal\mcgreen_square_sync\SaleStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Maps the Square catalog items that have been sold to Drupal products.
 */
class ItemsForm extends FormBase {

  use SaleDisplayTrait;

  /**
   * Prefix of an option that picks a package size by weight.
   */
  protected const WEIGHT_PREFIX = 'weight:';

  public function __construct(
    protected SaleFetcher $fetcher,
    protected SaleStorage $storage,
    protected DecisionStorage $decisions,
    protected ItemResolver $resolver,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CurrencyFormatter $currencyFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mcgreen_square_sync.fetcher'),
      $container->get('mcgreen_square_sync.storage'),
      $container->get('mcgreen_square_sync.decisions'),
      $container->get('mcgreen_square_sync.resolver'),
      $container->get('entity_type.manager'),
      $container->get('commerce_price.currency_formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcgreen_square_sync_items';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $sold = $this->soldItems();
    $maps = $this->decisions->getItemMaps();
    // Unmapped items first, then by name.
    uksort($sold, fn($a, $b) => [isset($maps[$a]), $sold[$a]['label']] <=> [isset($maps[$b]), $sold[$b]['label']]);

    $form['#cache'] = ['max-age' => 0];
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Say once what each Square item is; every sale of it follows. This is saved for when sales are imported and creates nothing yet. Not mapped: @unmapped of @total.', [
        '@unmapped' => count(array_diff_key($sold, $maps)),
        '@total' => count($sold),
      ]) . '</p>',
    ];
    $form['help'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('<strong>By weight</strong>: each line becomes one package of the smallest size that holds its weight.'),
        $this->t('<strong>A product</strong>: records either the quantity sold in Square, or one for each line.'),
        $this->t('<strong>No product</strong>: recorded under its Square name, with no stock change.'),
      ],
    ];

    $by_weight = [];
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
    foreach ($this->entityTypeManager->getStorage('commerce_product_variation')->loadMultiple() as $variation) {
      $product = $variation->getProduct();
      if ($product && $this->resolver->isByWeight($variation)) {
        $by_weight[self::WEIGHT_PREFIX . $product->id()] = (string) $this->t('@product – by weight', ['@product' => $product->label()]);
      }
    }
    natcasesort($by_weight);
    $options = [DecisionStorage::NO_PRODUCT => $this->t('No product (keep the Square name)')];
    if ($by_weight) {
      $options[(string) $this->t('Sold by weight')] = $by_weight;
    }
    // Package sizes are chosen by weight, so they are not offered one by one.
    $options += $this->variationOptions(fn($variation) => !$this->resolver->isByWeight($variation));

    $form['items'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        $this->t('Square item'),
        $this->t('Sold'),
        $this->t('Record as'),
        $this->t('Quantity (products)'),
        $this->t('Sizes (by weight)'),
      ],
      '#empty' => $this->t('No catalog items yet. Fetch sales from Square on the Sales tab.'),
    ];
    foreach ($sold as $id => $item) {
      $map = $maps[$id] ?? NULL;
      $default = match ($map['type'] ?? NULL) {
        DecisionStorage::PRODUCT => $map['target_id'],
        DecisionStorage::BY_WEIGHT => self::WEIGHT_PREFIX . $map['target_id'],
        DecisionStorage::NO_PRODUCT => DecisionStorage::NO_PRODUCT,
        default => '',
      };
      // The size each weight sold so far falls in.
      $sizes = [];
      if (($map['type'] ?? NULL) === DecisionStorage::BY_WEIGHT) {
        foreach ($item['quantities'] as $quantity) {
          $line = ['catalog_object_id' => $id, 'quantity' => $quantity, 'unit' => $item['unit']];
          $resolved = $this->resolver->resolve($line, [], $maps);
          $size = $resolved['type'] === ItemResolver::VARIATION ? $resolved['variation']->getTitle() : $this->t('no size fits');
          $sizes[] = $this->quantityText($quantity, $item['unit']) . ' → ' . $size;
        }
      }
      $form['items'][$id] = [
        'item' => ['#plain_text' => $item['label']],
        'sold' => [
          '#plain_text' => $this->formatPlural($item['lines'], '1 line', '@count lines') . ', ' . $this->money($item['total'], $item['currency'])
          . ($item['unit'] === ItemResolver::POUND ? ', ' . $this->t('by the pound') : ''),
        ],
        'target' => [
          '#type' => 'select',
          '#title' => $this->t('Record @item as', ['@item' => $item['label']]),
          '#title_display' => 'invisible',
          '#options' => $options,
          '#empty_option' => $this->t('- Not mapped -'),
          '#empty_value' => '',
          '#default_value' => $default,
        ],
        'quantity_rule' => [
          '#type' => 'select',
          '#title' => $this->t('Quantity for @item', ['@item' => $item['label']]),
          '#title_display' => 'invisible',
          '#options' => [
            DecisionStorage::SQUARE_QUANTITY => $this->t('Quantity sold'),
            DecisionStorage::ONE_PER_LINE => $this->t('1 per line'),
          ],
          '#default_value' => $map['quantity_rule'] ?? DecisionStorage::SQUARE_QUANTITY,
        ],
        'sizes' => $sizes ? ['#theme' => 'item_list', '#items' => $sizes] : ['#markup' => ''],
      ];
    }

    $form['actions'] = ['#type' => 'actions', '#access' => (bool) $sold];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save item mapping'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $sold = $this->soldItems();
    foreach ($form_state->getValue('items') ?: [] as $id => $values) {
      if (str_starts_with((string) $values['target'], self::WEIGHT_PREFIX) && ($sold[$id]['unit'] ?? NULL) !== ItemResolver::POUND) {
        $form_state->setErrorByName("items][$id][target", $this->t('@item is not sold by the pound, so it cannot be mapped by weight.', [
          '@item' => $sold[$id]['label'] ?? $id,
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    foreach ($form_state->getValue('items') ?: [] as $id => $values) {
      $target = (string) $values['target'];
      if ($target === DecisionStorage::NO_PRODUCT) {
        $this->decisions->setItemMap($id, DecisionStorage::NO_PRODUCT);
      }
      elseif (str_starts_with($target, self::WEIGHT_PREFIX)) {
        $this->decisions->setItemMap($id, DecisionStorage::BY_WEIGHT, (int) substr($target, strlen(self::WEIGHT_PREFIX)));
      }
      elseif (is_numeric($target)) {
        $this->decisions->setItemMap($id, DecisionStorage::PRODUCT, (int) $target, $values['quantity_rule']);
      }
      else {
        $this->decisions->setItemMap($id, NULL);
      }
    }
    $this->messenger()->addStatus($this->t('Saved the item mapping.'));
  }

  /**
   * Summarizes the catalog items in the stored sales.
   *
   * @return array[]
   *   Keyed by catalog object ID, each with "label" (the latest name), "unit",
   *   "currency", "lines", "total" (cents) and "quantities" (distinct, in
   *   ascending order).
   */
  protected function soldItems(): array {
    $items = [];
    // Newest first, so the first name seen is the current one.
    foreach ($this->storage->loadSince($this->fetcher->getStartDate()->getTimestamp()) as $sale) {
      foreach ($sale['items'] as $item) {
        $id = $item['catalog_object_id'] ?? NULL;
        if (!$id) {
          continue;
        }
        $label = $item['name'];
        if ($item['variation_name'] && !in_array($item['variation_name'], ['Regular', $item['name']], TRUE)) {
          $label .= ' (' . $item['variation_name'] . ')';
        }
        $items[$id] ??= [
          'label' => $label,
          'unit' => $item['unit'] ?? NULL,
          'currency' => $sale['currency'],
          'lines' => 0,
          'total' => 0,
          'quantities' => [],
        ];
        $items[$id]['lines']++;
        $items[$id]['total'] += $item['total'];
        $items[$id]['quantities'][$item['quantity']] = (string) $item['quantity'];
      }
    }
    foreach ($items as &$item) {
      $item['quantities'] = array_values($item['quantities']);
      sort($item['quantities'], SORT_NUMERIC);
    }
    return $items;
  }

}
