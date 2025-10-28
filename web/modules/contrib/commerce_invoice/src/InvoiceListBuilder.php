<?php

namespace Drupal\commerce_invoice;

use Drupal\commerce_invoice\Entity\InvoiceType;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for invoices.
 */
class InvoiceListBuilder extends EntityListBuilder {

  /**
   * The date service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Constructs a new InvoiceListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, DateFormatter $date_formatter) {
    parent::__construct($entity_type, $entity_type_manager->getStorage($entity_type->id()));

    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'invoice_number' => [
        'data' => $this->t('Invoice number'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'date' => [
        'data' => $this->t('Date'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'type' => [
        'data' => $this->t('Type'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'customer' => [
        'data' => $this->t('Customer'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'state' => [
        'data' => $this->t('State'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $invoice_type = InvoiceType::load($entity->bundle());
    $row = [
      'invoice_number' => $entity->label(),
      'date' => $this->dateFormatter->format($entity->getInvoiceDateTime(), 'short'),
      'type' => $invoice_type->label(),
      'customer' => [
        'data' => [
          '#theme' => 'username',
          '#account' => $entity->getCustomer(),
        ],
      ],
      'state' => $entity->getState()->getLabel(),
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $entity */
    if ($entity->access('view')) {
      $options = [
        'language' => $entity->language(),
      ];
      $operations['view'] = [
        'title' => $this->t('View'),
        'url' => $entity->toUrl('canonical', $options),
        'weight' => -50,
      ];

      $operations['download'] = [
        'title' => $this->t('Download'),
        'url' => $entity->toUrl('download', $options),
      ];
    }

    if ($entity->access('update') && !$entity->isPaid()) {
      $operations['pay'] = [
        'title' => $this->t('Pay'),
        'url' => $entity->toUrl('payment-form'),
        'weight' => 50,
      ];
    }

    if ($entity->access('resend_confirmation')) {
      $operations['resend_confirmation'] = [
        'title' => $this->t('Resend confirmation'),
        'url' => $entity->toUrl('resend-confirmation-form'),
        'weight' => 50,
      ];
    }

    return $operations;
  }

}
