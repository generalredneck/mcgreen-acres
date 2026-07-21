<?php

namespace Drupal\mcgreen_acres_quick_stock\Form;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_stock\StockServiceManager;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Quick add/remove stock form, meant to be opened in a modal dialog.
 */
class QuickStockAdjustForm extends FormBase {

  /**
   * The stock service manager.
   *
   * @var \Drupal\commerce_stock\StockServiceManager
   */
  protected $stockServiceManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The product variation being adjusted.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $productVariation;

  public function __construct(StockServiceManager $stock_service_manager, RequestStack $request_stack) {
    $this->stockServiceManager = $stock_service_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_stock.service_manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mcgreen_acres_quick_stock_adjust_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProductVariationInterface $commerce_product_variation = NULL) {
    $this->productVariation = $commerce_product_variation;

    $form['#attached']['library'][] = 'mcgreen_acres_quick_stock/quick_stock';
    $form['#attributes']['class'][] = 'quick-stock-adjust-form';
    $form['#prefix'] = '<div id="quick-stock-form-wrapper">';
    $form['#suffix'] = '</div>';

    $stock_service = $this->stockServiceManager->getService($commerce_product_variation);
    if ($stock_service->getId() === 'always_in_stock') {
      $form['message'] = [
        '#markup' => $this->t('This product is not managed by the stock system, so there is nothing to adjust.'),
      ];
      return $form;
    }

    $current_level = $this->stockServiceManager->getStockLevel($commerce_product_variation);

    $form['product_variation_id'] = [
      '#type' => 'value',
      '#value' => $commerce_product_variation->id(),
    ];

    $form['current_level'] = [
      '#type' => 'item',
      '#title' => $this->t('Current stock level'),
      '#markup' => '<div class="quick-stock-current-level">' . $current_level . '</div>',
    ];

    $form['quick_buttons'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['quick-stock-buttons', 'btn-group']],
    ];
    foreach ([-10, -1, 1, 10] as $delta) {
      // Plain HTML buttons (not FAPI '#type' => 'button') so they can never
      // trigger a form submission; JS only uses them to nudge the
      // adjustment field.
      $form['quick_buttons']['delta_' . $delta] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => ($delta > 0 ? '+' : '') . $delta,
        '#attributes' => [
          'type' => 'button',
          'class' => ['btn', 'btn-secondary', 'quick-stock-delta-button'],
          'data-delta' => $delta,
        ],
      ];
    }

    $form['adjustment'] = [
      '#type' => 'number',
      '#title' => $this->t('Adjustment'),
      '#description' => $this->t('Enter a positive number to add stock, or a negative number to remove stock.'),
      '#step' => 1,
      '#default_value' => 0,
      '#required' => TRUE,
      '#attributes' => ['class' => ['quick-stock-adjustment-input']],
    ];

    $form['transaction_note'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Note'),
      '#description' => $this->t('Optional note for this stock transaction.'),
      '#maxlength' => 255,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update stock'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $adjustment = $form_state->getValue('adjustment');
    if ($adjustment !== NULL && (float) $adjustment === 0.0) {
      $form_state->setErrorByName('adjustment', $this->t('Enter a non-zero adjustment.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $adjustment = (float) $form_state->getValue('adjustment');
    $note = $form_state->getValue('transaction_note');

    // Setting the stock field's value directly (rather than calling
    // receiveStock()/sellStock() ourselves) reuses
    // StockLevel::postSave(), which already knows how to resolve the
    // correct location/zone for this variation's store.
    $this->productVariation->set('stock', [
      'adjustment' => $adjustment,
      'stock_transaction_note' => $note,
    ]);
    $this->productVariation->save();

    $this->messenger()->addMessage($this->t('@qty stock adjustment applied to %variation.', [
      '@qty' => ($adjustment > 0 ? '+' : '') . $adjustment,
      '%variation' => $this->productVariation->label(),
    ]));
  }

  /**
   * AJAX submit handler: closes the modal and reloads the referring page.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#quick-stock-form-wrapper', $form));
      return $response;
    }

    $response->addCommand(new CloseModalDialogCommand());

    $referer = $this->requestStack->getCurrentRequest()->headers->get('referer');
    $redirect_url = $referer ?: $this->productVariation->getProduct()?->toUrl()->toString() ?: Url::fromRoute('<front>')->toString();
    $response->addCommand(new RedirectCommand($redirect_url));

    return $response;
  }

}
