<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce\EntityHelper;
use Drupal\commerce\Form\CommerceBundleEntityFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for shipment types.
 */
class ShipmentTypeForm extends CommerceBundleEntityFormBase {

  /**
   * The address book.
   *
   * @var \Drupal\commerce_order\AddressBookInterface
   */
  protected $addressBook;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->addressBook = $container->get('commerce_order.address_book');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\commerce_shipping\Entity\ShipmentTypeInterface $shipment_type */
    $shipment_type = $this->entity;
    $profile_types = $this->addressBook->loadTypes();
    $shipments_exist = FALSE;
    if (!$this->entity->isNew()) {
      $shipment_storage = $this->entityTypeManager->getStorage('commerce_shipment');
      $shipments_exist = (bool) $shipment_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $shipment_type->id())
        ->execute();
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $shipment_type->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $shipment_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\commerce_shipping\Entity\ShipmentType::load',
      ],
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
    ];
    $form['profileType'] = [
      '#type' => 'select',
      '#title' => $this->t('Profile type'),
      '#default_value' => $shipment_type->getProfileTypeId(),
      '#options' => EntityHelper::extractLabels($profile_types),
      '#required' => TRUE,
      '#disabled' => $shipments_exist,
    ];
    $form['emails']['sendConfirmation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send customer an email confirmation when shipped'),
      '#default_value' => $shipment_type->isNew() ? TRUE : $shipment_type->shouldSendConfirmation(),
    ];
    $form['emails']['confirmationBcc'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Send a copy of the shipment confirmation to this email:'),
      '#default_value' => $shipment_type->isNew() ? '' : $shipment_type->getConfirmationBcc(),
      '#states' => [
        'visible' => [
          ':input[name="sendConfirmation"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form = $this->buildTraitForm($form, $form_state);

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->validateTraitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = $this->entity->save();
    $this->submitTraitForm($form, $form_state);

    $this->messenger()->addStatus($this->t('Saved the %label shipment type.', [
      '%label' => $this->entity->label(),
    ]));
    $form_state->setRedirect('entity.commerce_shipment_type.collection');

    return $return;
  }

}
