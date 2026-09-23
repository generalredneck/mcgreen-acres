<?php

namespace Drupal\commerce_shipping\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_shipping_method' formatter.
 *
 * Represents the shipping method using the label of the selected service.
 */
#[FieldFormatter(
  id: 'commerce_shipping_method',
  label: new TranslatableMarkup('Shipping method'),
  field_types: ['entity_reference'],
)]
class ShippingMethodFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityRepository = $container->get('entity.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $items->getEntity();
    $shipping_service_id = $shipment->getShippingService();
    $shipping_service_label = $shipment->getShippingServiceLabel();

    $elements = [];
    foreach ($items as $delta => $item) {
      if (!empty($shipping_service_label)) {
        $elements[$delta] = [
          '#markup' => $shipping_service_label,
        ];
        continue;
      }
      /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
      $shipping_method = $item->entity;
      if (!$shipping_method) {
        // The shipping method could not be loaded, it was probably deleted.
        continue;
      }
      $shipping_method = $this->entityRepository->getTranslationFromContext($shipping_method, $langcode);
      $shipping_services = $shipping_method->getPlugin()->getServices();
      if (isset($shipping_services[$shipping_service_id])) {
        $elements[$delta] = [
          '#markup' => $shipping_services[$shipping_service_id]->getLabel(),
        ];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return $entity_type == 'commerce_shipment' && $field_name == 'shipping_method';
  }

}
