<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\entity\BundleFieldDefinition;
use Stripe\PaymentMethod;

/**
 * Provides the PayPal payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "stripe_paypal",
 *   label = @Translation("Stripe PayPal"),
 * )
 */
class PayPal extends StripePaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method): string {
    $label = $this->t('PayPal');
    $payer_email = $payment_method->get('stripe_paypal_payer_email')->getString();
    $payer_id = $payment_method->get('stripe_paypal_payer_id')->getString();
    $country = $payment_method->get('stripe_paypal_country')->getString();

    if (!empty($payer_email)) {
      $label .= ' ' . $this->t('(Email: @email)', ['@email' => $payer_email]);
    }
    elseif (!empty($payer_id)) {
      // Fall back to the id if the email is not available.
      $label .= ' ' . $this->t('(ID: @id)', ['@id' => $payer_id]);
    }
    if (!empty($country)) {
      $label .= ' ' . $this->t('(Country: @id)', ['@id' => $country]);
    }
    return $label ?: $this->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions(): array {
    $fields = parent::buildFieldDefinitions();

    $fields['stripe_paypal_payer_email'] = BundleFieldDefinition::create('email')
      ->setLabel(t('PayPal Payer Email'))
      ->setDescription(t('The payer email associated with the PayPal account.'))
      ->setRequired(TRUE);

    $fields['stripe_paypal_payer_id'] = BundleFieldDefinition::create('string')
      ->setLabel(t('PayPal Payer Id'))
      ->setDescription(t('The payer id associated with the PayPal account.'))
      ->setRequired(TRUE);

    $fields['stripe_paypal_country'] = BundleFieldDefinition::create('string')
      ->setSetting('max_length', 2)
      ->setLabel(t('PayPal Country Code'))
      ->setDescription(t('The payer country associated with the PayPal account.'))
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method, PaymentMethod $stripe_payment_method): void {
    if (!$payment_method->getType() instanceof self) {
      return;
    }
    $paypal_payer_email = $stripe_payment_method->paypal?->payer_email ?? NULL;
    if (!empty($paypal_payer_email)) {
      $payment_method->set('stripe_paypal_payer_email', $paypal_payer_email);
    }
    $paypal_payer_id = $stripe_payment_method->paypal?->payer_id ?? NULL;
    if (!empty($paypal_payer_id)) {
      $payment_method->set('stripe_paypal_payer_id', $paypal_payer_id);
    }
    $paypal_country = $stripe_payment_method->paypal?->country ?? NULL;
    if (!empty($paypal_country)) {
      $payment_method->set('stripe_paypal_country', $paypal_country);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLogos(): array {
    return [
      'paypal' => $this->t('Paypal'),
    ];
  }

}
