<?php

namespace Drupal\commerce_shipping\Plugin\Field\FieldFormatter;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_shipping_information' formatter.
 */
#[FieldFormatter(
  id: 'commerce_shipping_information',
  label: new TranslatableMarkup('Shipping information'),
  field_types: ['entity_reference'],
)]
class ShippingInformationFormatter extends FormatterBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $items->referencedEntities();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $items->getEntity();

    // Generate "order information" initial badge.
    $badge = [];
    if (!empty($shipments)) {
      $shipment = reset($shipments);
      if ($shipment->access('update')) {
        $badge = [
          '#type' => 'link',
          '#title' => $this->t('Edit'),
          '#url' => $shipment->toUrl('edit-modal-form'),
          '#attributes' => [
            'class' => ['use-ajax', 'commerce-edit-link'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode([
              'width' => 880,
            ]),
          ],
        ];
      }
    }
    $component = [
      '#type' => 'component',
      '#component' => 'commerce:commerce-admin-card',
      '#props' => [
        'title' => $this->t('Shipping information'),
        'id' => 'shipping-information-admin-card',
        'badge' => $badge,
      ],
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
    ];

    $shipment_collection_url = Url::fromRoute('entity.commerce_shipment.collection', ['commerce_order' => $order->id()]);
    if (!empty($shipments)) {
      $shipment_view_builder = $this->entityTypeManager->getViewBuilder('commerce_shipment');
      $component['#slots']['card_content'] = $shipment_view_builder->view(reset($shipments), 'admin');
      if ($shipment_collection_url->access()) {
        $component['#slots']['card_footer']['manage_shipments_link'] = [
          '#type' => 'link',
          '#title' => $this->formatPlural(count($shipments), 'Manage shipment →', 'Manage shipments →'),
          '#url' => $shipment_collection_url,
        ];
      }
    }
    else {
      $add_shipment_modal_link = $this->getAddShipmentModalLink($order);
      if ($add_shipment_modal_link) {
        $component['#slots']['card_content'] = $add_shipment_modal_link->toRenderable();
      }
      else {
        $component['#slots']['card_content'] = [
          '#markup' => '-',
        ];
      }
    }

    return [$component];
  }

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL) {
    $elements = parent::view($items, $langcode);
    // Be sure the field's label is hidden even if it's enabled on view mode.
    $elements['#label_display'] = 'hidden';
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getTargetEntityTypeId() === 'commerce_order' && $field_definition->getName() === 'shipments';
  }

  /**
   * Returns link object to add a new shipment in modal.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   */
  private function getAddShipmentModalLink(OrderInterface $order): ?Link {
    $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $order_type_storage->load($order->bundle());
    $add_shipment_url = Url::fromRoute(
      'entity.commerce_shipment.add_modal_form',
      [
        'commerce_order' => $order->id(),
        'commerce_shipment_type' => $order_type->getThirdPartySetting('commerce_shipping', 'shipment_type'),
      ]
    );
    if ($add_shipment_url->access()) {
      $add_shipment_url->setOption('attributes', [
        'class' => ['button', 'button--primary', 'use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 880,
        ]),
      ]);
      return Link::fromTextAndUrl('Add shipping information', $add_shipment_url);
    }

    return NULL;
  }

}
