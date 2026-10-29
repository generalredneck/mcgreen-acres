<?php

declare(strict_types=1);

namespace Drupal\commerce_stripe\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;

/**
 * Checks installation requirements for Commerce Stripe.
 */
class CommerceStripeRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    $requirements = [];
    if (!class_exists('\Stripe\Stripe')) {
      $requirements['commerce_stripe_library'] = [
        'description' => t('Commerce Stripe requires the stripe/stripe-php library.'),
        'severity' => RequirementSeverity::Error,
      ];
    }
    return $requirements;
  }

}
