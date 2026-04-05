<?php

namespace Drupal\commerce_shipping;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Event\ShippingEvents;
use Drupal\commerce_shipping\Event\ShippingRatesEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a helper service for managing shipments.
 */
class ShipmentManager implements ShipmentManagerInterface {

  /**
   * Constructs a new ShipmentManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected EventDispatcherInterface $eventDispatcher,
    #[Autowire(service: 'logger.channel.commerce_shipping')]
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function applyRate(ShipmentInterface $shipment, ShippingRate $rate) {
    $shipping_method_storage = $this->entityTypeManager->getStorage('commerce_shipping_method');
    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
    $shipping_method = $shipping_method_storage->load($rate->getShippingMethodId());
    $shipping_method_plugin = $shipping_method->getPlugin();
    if (empty($shipment->getPackageType())) {
      $shipment->setPackageType($shipping_method_plugin->getDefaultPackageType());
    }
    $shipping_method_plugin->selectRate($shipment, $rate);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    $all_rates = [];
    /** @var \Drupal\commerce_shipping\ShippingMethodStorageInterface $shipping_method_storage */
    $shipping_method_storage = $this->entityTypeManager->getStorage('commerce_shipping_method');
    $shipping_methods = $shipping_method_storage->loadMultipleForShipment($shipment);
    foreach ($shipping_methods as $shipping_method) {
      /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
      $shipping_method = $this->entityRepository->getTranslationFromContext($shipping_method);
      $shipping_method_plugin = $shipping_method->getPlugin();
      try {
        $rates = $shipping_method_plugin->calculateRates($shipment);
      }
      catch (\Exception $exception) {
        $this->logger->error('Exception occurred when calculating rates for @name: @message', [
          '@name' => $shipping_method->getName(),
          '@message' => $exception->getMessage(),
        ]);
        continue;
      }
      // Allow the rates to be altered via code.
      $event = new ShippingRatesEvent($rates, $shipping_method, $shipment);
      $this->eventDispatcher->dispatch($event, ShippingEvents::SHIPPING_RATES);
      $rates = $event->getRates();

      $rates = $this->sortRates($rates);
      foreach ($rates as $rate) {
        $all_rates[$rate->getId()] = $rate;
      }
    }

    return $all_rates;
  }

  /**
   * {@inheritdoc}
   */
  public function selectDefaultRate(ShipmentInterface $shipment, array $rates) {
    /** @var \Drupal\commerce_shipping\ShippingRate[] $rates */
    $default_rate = reset($rates);
    if ($shipment->getShippingMethodId() && $shipment->getShippingService()) {
      // Select the first rate which matches the shipment's selected
      // shipping method and service.
      foreach ($rates as $rate) {
        if ($shipment->getShippingMethodId() != $rate->getShippingMethodId()) {
          continue;
        }
        if ($shipment->getShippingService() != $rate->getService()->getId()) {
          continue;
        }
        $default_rate = $rate;
        break;
      }
    }

    return $default_rate;
  }

  /**
   * Sorts the given rates.
   *
   * @param \Drupal\commerce_shipping\ShippingRate[] $rates
   *   The rates.
   *
   * @return \Drupal\commerce_shipping\ShippingRate[]
   *   The sorted rates.
   */
  protected function sortRates(array $rates) {
    // Sort by original_amount ascending.
    uasort($rates, function (ShippingRate $first_rate, ShippingRate $second_rate) {
      return $first_rate->getOriginalAmount()->compareTo($second_rate->getOriginalAmount());
    });

    return $rates;
  }

}
