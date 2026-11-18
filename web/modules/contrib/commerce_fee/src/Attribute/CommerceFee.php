<?php

declare(strict_types=1);

namespace Drupal\commerce_fee\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommerceFee attribute.
 *
 * Additional attribute keys for fee plugins can be defined in
 * hook_commerce_fee_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommerceFee extends Plugin {

  /**
   * Constructs a CommerceFee attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The fee plugin label.
   * @param string $entity_type
   *   The fee entity type ID. This is the entity type ID of the entity
   *   passed to the plugin during execution.
   *   For example: 'commerce_order'.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly string $entity_type,
  ) {
  }

}
