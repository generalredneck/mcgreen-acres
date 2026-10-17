<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\entity\BundleFieldDefinition;
use Stripe\PaymentMethod;

/**
 * Provides the stripe link payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "stripe_link",
 *   label = @Translation("Link"),
 * )
 */
class Link extends StripePaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method): string {
    $args = [
      '@stripe_link_email' => $payment_method->get('stripe_link_email')->getString(),
    ];
    return $this->t('Stripe Link with email @stripe_link_email', $args)->render();
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions(): array {
    $fields = parent::buildFieldDefinitions();

    $fields['stripe_link_email'] = BundleFieldDefinition::create('email')
      ->setLabel(t('Link email'))
      ->setDescription(t('The Stripe link email'))
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method, PaymentMethod $stripe_payment_method): void {
    $link = $stripe_payment_method->link;
    $payment_method->set('stripe_link_email', $link->email ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function getLogos(): array {
    return [
      'link' => $this->t('Link'),
    ];
  }

}
