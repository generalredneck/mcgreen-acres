<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\entity\BundleFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\CreditCard;
use Drupal\commerce_payment\Attribute\CommercePaymentMethodType;

/**
 * Provides the credit card payment method type.
 */
#[CommercePaymentMethodType(
  id: "credit_card",
  label: new TranslatableMarkup('Credit card'),
)]
class LegacyCard extends CreditCard {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['stripe_customer_id'] = BundleFieldDefinition::create('string')
      ->setLabel($this->t('Card Customer Id'))
      ->setDescription($this->t('The remote customer ID for the card.'))
      ->setSetting('max_length', 255);

    return $fields;
  }

}
