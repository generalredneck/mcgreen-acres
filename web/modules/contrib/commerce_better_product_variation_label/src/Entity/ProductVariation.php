<?php

namespace Drupal\commerce_better_product_variation_label\Entity;

use Drupal\commerce_product\Entity\ProductVariation as CommerceProductVariation;
use Drupal\commerce_product\Entity\ProductVariationInterface;

/**
 *
 */
class ProductVariation extends CommerceProductVariation implements ProductVariationInterface {

  /**
   * The product variation label separator.
   *
   * @var string
   */
  protected $labelSeparator = ' ';

  /**
   * {@inheritDoc}
   */
  public function label() {
    /** @var \Drupal\commerce_product\Entity\ProductVariationTypeInterface $entityType */
    $entityType = $this->type->entity;
    $enabled = $entityType->getThirdPartySetting('commerce_better_product_variation_label', 'product_label_prefix', FALSE);
    $parentProduct = $this->getProduct();
    $commerceProductVariationLabel = parent::label();
    if ($enabled && !empty($parentProduct) && $parentProduct->label() !== $commerceProductVariationLabel) {
      $separator = $entityType->getThirdPartySetting('commerce_better_product_variation_label', 'product_label_prefix_separator', ' ');

      // If applies, prefix the product variation with the parent product label.
      return $parentProduct->label() . $separator . $commerceProductVariationLabel;
    }

    // Fallback to default label() method otherwise:
    return $commerceProductVariationLabel;
  }

}
