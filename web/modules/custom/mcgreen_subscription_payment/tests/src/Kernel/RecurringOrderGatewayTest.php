<?php

namespace Drupal\Tests\mcgreen_subscription_payment\Kernel;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce_recurring\Kernel\RecurringKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests which payment gateway a manual-payment renewal order carries.
 */
#[Group('mcgreen_subscription_payment')]
#[RunTestsInSeparateProcesses]
class RecurringOrderGatewayTest extends RecurringKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mcgreen_subscription_payment',
  ];

  /**
   * The draft renewal order of a subscription paid in cash.
   */
  protected OrderInterface $order;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    foreach (['cash', 'zelle'] as $gateway_id) {
      PaymentGateway::create([
        'id' => $gateway_id,
        'label' => ucfirst($gateway_id),
        'plugin' => 'manual',
      ])->save();
    }

    $initial_order = $this->createInitialOrder();
    $initial_order->set('payment_gateway', 'cash');
    $initial_order->getState()->applyTransitionById('place');
    $initial_order->save();

    $order_storage = $this->container->get('entity_type.manager')->getStorage('commerce_order');
    $order_ids = $order_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'recurring')
      ->execute();
    $this->assertCount(1, $order_ids);
    $this->order = $order_storage->load(reset($order_ids));
    $this->assertEquals('draft', $this->order->getState()->getId());
    $this->assertGateway('cash');
  }

  /**
   * Tests that a received payment's gateway survives later saves and placement.
   */
  public function testReceivedPaymentOnDraft(): void {
    $this->addPayment('zelle', TRUE);
    $this->assertGateway('zelle');

    $this->order->save();
    $this->assertGateway('zelle');

    $this->order->getState()->applyTransitionById('place');
    $this->order->save();
    $this->order->getState()->applyTransitionById('mark_paid');
    $this->order->save();
    $this->assertGateway('zelle');
  }

  /**
   * Tests that a payment not yet received sets the gateway too.
   */
  public function testPendingPaymentOnDraft(): void {
    $this->addPayment('zelle', FALSE);
    $this->assertGateway('zelle');
  }

  /**
   * Tests that the latest payment wins and a voided one stops counting.
   */
  public function testLatestPaymentWinsUntilVoided(): void {
    $this->addPayment('zelle', TRUE, '1.00');
    $payment = $this->addPayment('cash', FALSE, '1.00');
    $this->assertGateway('cash');

    $latest = $this->addPayment('zelle', FALSE, '1.00');
    $this->assertGateway('zelle');

    $latest->setState('voided')->save();
    $this->order->save();
    $this->assertGateway('cash');

    $payment->setState('voided')->save();
    $this->order->save();
    $this->assertGateway('zelle');
  }

  /**
   * Tests that the subscription's gateway applies without a usable payment.
   */
  public function testVoidedPaymentFallsBackToSubscription(): void {
    $payment = $this->addPayment('zelle', FALSE);
    $payment->setState('voided')->save();
    $this->order->save();
    $this->assertGateway('cash');
  }

  /**
   * Records a payment the way the admin "Add payment" form does.
   *
   * @param string $gateway_id
   *   The manual payment gateway ID.
   * @param bool $received
   *   Whether the money has been received.
   * @param string $amount
   *   The amount in USD.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface
   *   The saved payment.
   */
  protected function addPayment(string $gateway_id, bool $received, string $amount = '6.00'): PaymentInterface {
    $payment = Payment::create([
      'order_id' => $this->order->id(),
      'payment_gateway' => $gateway_id,
      'amount' => new Price($amount, 'USD'),
    ]);
    $payment->getPaymentGateway()->getPlugin()->createPayment($payment, $received);
    $this->order = $this->reloadEntity($this->order);
    $this->order->set('payment_gateway', $gateway_id);
    $this->order->save();

    return $payment;
  }

  /**
   * Asserts the gateway stored on the renewal order.
   *
   * @param string $gateway_id
   *   The expected payment gateway ID.
   */
  protected function assertGateway(string $gateway_id): void {
    $this->order = $this->reloadEntity($this->order);
    $this->assertEquals($gateway_id, $this->order->get('payment_gateway')->target_id);
  }

}
