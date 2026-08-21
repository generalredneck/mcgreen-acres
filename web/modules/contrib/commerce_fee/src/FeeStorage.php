<?php

namespace Drupal\commerce_fee;

use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the fee storage.
 */
class FeeStorage extends CommerceContentEntityStorage implements FeeStorageInterface {

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadAvailable(OrderInterface $order): array {
    $date = $order->getCalculationDate()->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query = $this->getQuery();
    $or_condition = $query->orConditionGroup()
      ->condition('end_date', $date, '>')
      ->notExists('end_date');
    $query
      ->condition('stores', [$order->getStoreId()], 'IN')
      ->condition('order_types', [$order->bundle()], 'IN')
      ->condition('start_date', $date, '<=')
      ->condition('status', TRUE)
      ->condition($or_condition)
      ->accessCheck(FALSE);
    $result = $query->accessCheck()->execute();
    if (empty($result)) {
      return [];
    }

    return $this->loadMultiple($result);
  }

}
