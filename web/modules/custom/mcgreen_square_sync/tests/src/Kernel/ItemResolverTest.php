<?php

namespace Drupal\Tests\mcgreen_square_sync\Kernel;

use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mcgreen_square_sync\DecisionStorage;
use Drupal\mcgreen_square_sync\ItemResolver;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests what lines of a Square sale are recorded as.
 *
 * @group mcgreen_square_sync
 * @covers \Drupal\mcgreen_square_sync\ItemResolver
 * @covers \Drupal\mcgreen_square_sync\DecisionStorage
 */
#[RunTestsInSeparateProcesses]
class ItemResolverTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_payment',
    'key',
    'mcgreen_square_sync',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('mcgreen_square_sync', [
      'mcgreen_square_link',
      'mcgreen_square_line_map',
      'mcgreen_square_item_map',
    ]);
    FieldStorageConfig::create([
      'field_name' => 'field_max_weight',
      'entity_type' => 'commerce_product_variation',
      'type' => 'decimal',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_max_weight',
      'entity_type' => 'commerce_product_variation',
      'bundle' => 'default',
    ])->save();
  }

  /**
   * Creates a variation, optionally a package with a maximum weight.
   */
  protected function variation(string $sku, ?string $max_weight = NULL): ProductVariation {
    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => $sku,
      'title' => $sku,
      'field_max_weight' => $max_weight,
    ]);
    $variation->save();
    return $variation;
  }

  /**
   * A stored catalog line.
   */
  protected function line(string $quantity, ?string $unit = ItemResolver::POUND): array {
    return ['catalog_object_id' => 'CAT', 'quantity' => $quantity, 'unit' => $unit];
  }

  /**
   * Resolves a line using the stored catalog mappings.
   */
  protected function resolve(array $line, array $line_maps = []): array {
    $result = $this->container->get('mcgreen_square_sync.resolver')->resolve(
      $line,
      $line_maps,
      $this->container->get('mcgreen_square_sync.decisions')->getItemMaps(),
    );
    if (isset($result['variation'])) {
      $result['variation'] = $result['variation']->getSku();
    }
    return $result;
  }

  /**
   * A weighed line takes the smallest package that holds it.
   */
  public function testByWeight(): void {
    // Out of order, so the resolver has to sort them.
    $sizes = [
      $this->variation('large', '5'),
      $this->variation('small', '3'),
      $this->variation('medium', '4'),
    ];
    $product = Product::create(['type' => 'default', 'title' => 'Chicken', 'variations' => $sizes]);
    $product->save();
    $decisions = $this->container->get('mcgreen_square_sync.decisions');
    $decisions->setItemMap('CAT', DecisionStorage::BY_WEIGHT, (int) $product->id());

    foreach (['0.5' => 'small', '3' => 'small', '3.01' => 'medium', '4.38' => 'large', '5.00' => 'large'] as $weight => $size) {
      $this->assertSame(
        ['type' => 'variation', 'variation' => $size, 'quantity' => '1'],
        $this->resolve($this->line((string) $weight)),
        "Weight $weight",
      );
    }
    $this->assertSame(['type' => 'no_size'], $this->resolve($this->line('5.03')));
    // Something counted, not weighed, has no size.
    $this->assertSame(['type' => 'no_size'], $this->resolve($this->line('1', NULL)));

    $product->delete();
    $this->assertSame([], $decisions->getItemMaps());
    $this->assertSame(['type' => 'unmapped'], $this->resolve($this->line('1')));
  }

  /**
   * A fixed product records the quantity sold, or one per line.
   */
  public function testProductAndNoProduct(): void {
    $variation = $this->variation('bag');
    $decisions = $this->container->get('mcgreen_square_sync.decisions');
    $this->assertSame(['type' => 'unmapped'], $this->resolve($this->line('0.33')));

    $decisions->setItemMap('CAT', DecisionStorage::PRODUCT, (int) $variation->id());
    $this->assertSame('0.33', $this->resolve($this->line('0.33'))['quantity']);

    $decisions->setItemMap('CAT', DecisionStorage::PRODUCT, (int) $variation->id(), DecisionStorage::ONE_PER_LINE);
    $this->assertSame(
      ['type' => 'variation', 'variation' => 'bag', 'quantity' => '1'],
      $this->resolve($this->line('0.33')),
    );

    $decisions->setItemMap('CAT', DecisionStorage::NO_PRODUCT, 99, DecisionStorage::ONE_PER_LINE);
    $this->assertSame(
      ['type' => 'none', 'target_id' => NULL, 'quantity_rule' => NULL],
      $decisions->getItemMaps()['CAT'],
    );
    $this->assertSame(['type' => 'none'], $this->resolve($this->line('0.33')));

    $decisions->setItemMap('CAT', DecisionStorage::PRODUCT, (int) $variation->id());
    $variation->delete();
    $this->assertSame([], $decisions->getItemMaps());
  }

  /**
   * Lines without a catalog item follow their own mapping.
   */
  public function testCustomAmount(): void {
    $variation = $this->variation('eggs');
    $line = ['catalog_object_id' => NULL, 'uid' => 'u1', 'quantity' => '1'];
    $this->assertSame(['type' => 'unmapped'], $this->resolve($line));
    $this->assertSame(['type' => 'adjustment'], $this->resolve($line, [
      'u1' => ['type' => 'adjustment', 'variation_id' => NULL, 'quantity' => NULL],
    ]));
    $this->assertSame(
      ['type' => 'variation', 'variation' => 'eggs', 'quantity' => '3'],
      $this->resolve($line, [
        'u1' => ['type' => 'product', 'variation_id' => (int) $variation->id(), 'quantity' => '3'],
      ]),
    );
  }

}
