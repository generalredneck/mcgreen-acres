<?php

declare(strict_types=1);

namespace Drupal\commerce_shipping\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommerceShippingMethod attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommerceShippingMethod extends Plugin {

  /**
   * Constructs a CommerceShippingMethod attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label.
   * @param array $services
   *   The supported shipping services. An array of labels keyed by ID.
   * @param string $workflow
   *   The shipment workflow.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly array $services = [],
    public readonly string $workflow = 'shipment_default',
    public readonly ?string $deriver = NULL,
  ) {
  }

}
