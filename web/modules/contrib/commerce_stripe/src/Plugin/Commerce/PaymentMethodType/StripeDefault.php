<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Attribute\CommercePaymentMethodType;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_stripe\Enum\StripePaymentMethodType as StripePaymentMethodTypeEnum;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Stripe\PaymentMethod;

/**
 * Provides a fallback type for unsupported Stripe payment methods.
 *
 * When Stripe enables a payment method type that does not have a dedicated
 * plugin, this type is used to avoid throwing an exception at checkout
 * completion. The full Stripe PaymentMethod JSON is stored so that any
 * identifying information is preserved for order review and debugging.
 */
#[CommercePaymentMethodType(
  id: 'stripe_default',
  label: new TranslatableMarkup('Stripe Payment'),
)]
class StripeDefault extends StripePaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method): string {
    $type = $payment_method->get('stripe_payment_method_type')->getString();
    if (!empty($type)) {
      $enum = StripePaymentMethodTypeEnum::tryFrom($type);
      return $enum !== NULL ? $enum->label() : $type;
    }
    return $this->t('Stripe Payment')->render();
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions(): array {
    $fields = parent::buildFieldDefinitions();

    $fields['stripe_payment_method_type'] = BundleFieldDefinition::create('list_string')
      ->setLabel(t('Stripe payment method type'))
      ->setDescription(t('The Stripe payment method type.'))
      ->setSetting('allowed_values_function', [StripePaymentMethodTypeEnum::class, 'options'])
      ->setRequired(FALSE);

    $fields['stripe_payment_method_json'] = BundleFieldDefinition::create('string_long')
      ->setLabel(t('Stripe payment method JSON'))
      ->setDescription(t('The full Stripe PaymentMethod object as JSON.'))
      ->setRequired(FALSE)
      ->setInternal(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method, PaymentMethod $stripe_payment_method): void {
    $payment_method->set('stripe_payment_method_type', $stripe_payment_method->type);
    $payment_method->set('stripe_payment_method_json', json_encode($stripe_payment_method->toArray()));
  }

  /**
   * {@inheritdoc}
   */
  public function getLogos(): array {
    return [];
  }

}
