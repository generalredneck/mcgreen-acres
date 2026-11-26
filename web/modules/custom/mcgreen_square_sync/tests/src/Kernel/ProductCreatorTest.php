<?php

namespace Drupal\Tests\mcgreen_square_sync\Kernel;

use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests creating products for Square items.
 *
 * @group mcgreen_square_sync
 * @covers \Drupal\mcgreen_square_sync\ProductCreator
 */
#[RunTestsInSeparateProcesses]
class ProductCreatorTest extends SquareSyncKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ProductVariationType::create([
      'id' => 'physical',
      'label' => 'Physical',
      'orderItemType' => 'test',
      'generateTitle' => FALSE,
    ])->save();
    ProductType::create([
      'id' => 'physical',
      'label' => 'Physical',
      'variationTypes' => ['physical'],
    ])->save();
  }

  /**
   * A Square item as the Items tab describes it.
   */
  protected function item(string $name, string $variation_name, int $price, ?string $unit = NULL): array {
    return [
      'name' => $name,
      'variation_name' => $variation_name,
      'unit' => $unit,
      'price' => $price,
      'currency' => 'USD',
    ];
  }

  /**
   * Variations of one item share an unpublished product.
   */
  public function testCreate(): void {
    // Takes the SKU the new radishes would otherwise get.
    ProductVariation::create(['type' => 'physical', 'sku' => 'radishes-per-lb', 'title' => 'Old'])->save();

    $variations = $this->container->get('mcgreen_square_sync.product_creator')->create([
      'RADISH' => $this->item('Radishes ', 'Regular', 200, 'IMPERIAL_POUND'),
      'SMALL' => $this->item('Jam', '8 oz', 700),
      'BIG' => $this->item('Jam', '16 oz', 1200),
      'CHOY' => $this->item('Bok Choy', 'Regular', 250),
    ]);

    $this->assertSame(['RADISH', 'SMALL', 'BIG', 'CHOY'], array_keys($variations));
    $radish = $variations['RADISH'];
    $this->assertSame('Per lb', $radish->getTitle());
    $this->assertSame('radishes-per-lb-2', $radish->getSku());
    $this->assertEquals(new Price('2.00', 'USD'), $radish->getPrice());
    $product = $radish->getProduct();
    $this->assertSame('Radishes', $product->label());
    $this->assertFalse($product->isPublished());
    $this->assertEquals([$this->store->id()], $product->getStoreIds());

    $this->assertSame('8 oz', $variations['SMALL']->getTitle());
    $this->assertSame('jam-16-oz', $variations['BIG']->getSku());
    $this->assertSame($variations['SMALL']->getProductId(), $variations['BIG']->getProductId());
    $this->assertCount(2, $variations['BIG']->getProduct()->getVariations());
    $this->assertSame('Each', $variations['CHOY']->getTitle());
  }

  /**
   * Existing product names are reported, whatever their case or spacing.
   */
  public function testTakenNames(): void {
    $creator = $this->container->get('mcgreen_square_sync.product_creator');
    $items = [$this->item('Honey', '1 lb', 2000), $this->item('Figs', 'Regular', 500)];
    $this->assertSame([], $creator->getTakenNames($items));

    $creator->create([$items[0]]);
    $this->assertSame(['honey'], $creator->getTakenNames([$this->item(' honey ', '4 oz', 700), $items[1]]));
  }

}
