<?php

declare(strict_types=1);

namespace Drupal\commerce_shipping\Entity;

use Drupal\commerce\Entity\CommerceBundleEntityBase;

/**
 * Defines the shipment type entity class.
 *
 * @ConfigEntityType(
 *   id = "commerce_shipment_type",
 *   label = @Translation("Shipment type"),
 *   label_collection = @Translation("Shipment types"),
 *   label_singular = @Translation("shipment type"),
 *   label_plural = @Translation("shipment types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count shipment type",
 *     plural = "@count shipment types",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\commerce_shipping\ShipmentTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\commerce_shipping\Form\ShipmentTypeForm",
 *       "edit" = "Drupal\commerce_shipping\Form\ShipmentTypeForm",
 *       "delete" = "Drupal\commerce_shipping\Form\ShipmentTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer commerce_shipment_type",
 *   config_prefix = "commerce_shipment_type",
 *   bundle_of = "commerce_shipment",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *     "profileType",
 *     "traits",
 *     "sendConfirmation",
 *     "confirmationBcc",
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/config/shipment-types/add",
 *     "edit-form" = "/admin/commerce/config/shipment-types/{commerce_shipment_type}/edit",
 *     "delete-form" = "/admin/commerce/config/shipment-types/{commerce_shipment_type}/delete",
 *     "collection" = "/admin/commerce/config/shipment-types",
 *   }
 * )
 */
class ShipmentType extends CommerceBundleEntityBase implements ShipmentTypeInterface {

  /**
   * The profile type ID.
   *
   * @var string
   */
  protected string $profileType = 'customer';

  /**
   * Shipping confirmation email enabled.
   *
   * @var bool|null
   */
  protected ?bool $sendConfirmation = NULL;

  /**
   * Shipping confirmation BCC email address.
   *
   * @var string|null
   */
  protected ?string $confirmationBcc = NULL;

  /**
   * {@inheritdoc}
   */
  public function getProfileTypeId(): string {
    return $this->profileType;
  }

  /**
   * {@inheritdoc}
   */
  public function setProfileTypeId(string $profile_type_id): static {
    $this->profileType = $profile_type_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldSendConfirmation(): ?bool {
    return $this->sendConfirmation;
  }

  /**
   * {@inheritdoc}
   */
  public function setSendConfirmation(bool $send_confirmation): static {
    $this->sendConfirmation = $send_confirmation;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmationBcc(): ?string {
    return $this->confirmationBcc;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfirmationBcc(string $confirmation_bcc): static {
    $this->confirmationBcc = $confirmation_bcc;
    return $this;
  }

}
