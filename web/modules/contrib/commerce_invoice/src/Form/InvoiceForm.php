<?php

namespace Drupal\commerce_invoice\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the commerce_invoice entity edit forms.
 */
class InvoiceForm extends ContentEntityForm {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new OrderForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, DateFormatterInterface $date_formatter) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $this->entity;
    $form = parent::form($form, $form_state);

    $invoice_type = $this->entityTypeManager->getStorage('commerce_invoice_type')->load($invoice->bundle());
    $form['#title'] = $this->t('Add new @invoice_type_label', ['@invoice_type_label' => $invoice_type->label()]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $save = $this->entity->save();
    $this->messenger()->addMessage($this->t('The %label has been successfully saved.', ['%label' => $this->entity->label()]));

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entity->get('orders')->get(0)->entity;
    $redirect_url = $order->toUrl('canonical');
    if ($this->entity->bundle() == 'default') {
      $redirect_url = $order->toUrl('invoices');
    }
    elseif ($this->entity->bundle() == 'credit_memo') {
      $redirect_url = $order->toUrl('credit-memos');
    }
    $form_state->setRedirectUrl($redirect_url);

    return $save;
  }

}
