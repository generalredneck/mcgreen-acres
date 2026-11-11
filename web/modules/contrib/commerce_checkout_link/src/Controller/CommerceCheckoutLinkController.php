<?php

namespace Drupal\commerce_checkout_link\Controller;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_checkout\CheckoutOrderManagerInterface;
use Drupal\commerce_checkout_link\CheckoutLinkManager;
use Drupal\commerce_checkout_link\Event\CheckoutLinkEvent;
use Drupal\commerce_checkout_link\Event\CommerceCheckoutLinkEvents;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderAssignmentInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Returns responses for Commerce Checkout Link routes.
 */
class CommerceCheckoutLinkController extends ControllerBase {

  /**
   * Time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Cart session.
   *
   * @var \Drupal\commerce_cart\CartSessionInterface
   */
  protected $cartSession;

  /**
   * Order assignment.
   *
   * @var \Drupal\commerce_order\OrderAssignmentInterface
   */
  protected $orderAssignment;

  /**
   * Checkout order manager.
   *
   * @var \Drupal\commerce_checkout\CheckoutOrderManagerInterface
   */
  protected $checkoutOrderManager;

  /**
   * Cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * CommerceCheckoutLinkController constructor.
   */
  public function __construct(TimeInterface $time, AccountProxyInterface $current_user, CartSessionInterface $cart_session, OrderAssignmentInterface $orderAssignment, EntityTypeManagerInterface $entityTypeManager, CheckoutOrderManagerInterface $checkoutOrderManager, CartProviderInterface $cartProvider, ModuleHandlerInterface $moduleHandler, LoggerChannelFactoryInterface $loggerChannelFactory, EventDispatcherInterface $event_dispatcher) {
    $this->time = $time;
    $this->currentUser = $current_user;
    $this->cartSession = $cart_session;
    $this->orderAssignment = $orderAssignment;
    $this->entityTypeManager = $entityTypeManager;
    $this->checkoutOrderManager = $checkoutOrderManager;
    $this->cartProvider = $cartProvider;
    $this->moduleHandler = $moduleHandler;
    $this->loggerFactory = $loggerChannelFactory;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('commerce_cart.cart_session'),
      $container->get('commerce_order.order_assignment'),
      $container->get('entity_type.manager'),
      $container->get('commerce_checkout.checkout_order_manager'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('module_handler'),
      $container->get('logger.factory'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Builds the response.
   */
  public function checkout(OrderInterface $commerce_order, $timestamp, $hash) {
    $current_time = $this->time->getCurrentTime();
    $timeout = 24 * 3600;
    $this->moduleHandler->alter('commerce_checkout_link_timeout', $timeout);
    $logger = $this->getLogger('commerce_checkout_link');
    if ($current_time - $timestamp > $timeout) {
      $logger->info('A user used an expired checkout link. Timeout was @timestamp', [
        '@timestamp' => $timestamp,
      ]);
      throw new AccessDeniedHttpException('The link provided is no longer valid');
    }
    // If we do not have a hash, we do not allow this.
    if (!$hash) {
      $logger->info('A user used a checkout link with no hash');
      throw new AccessDeniedHttpException('The hash provided was not valid');
    }
    // If the hash does not look like we want, let's not allow it.
    $config = $this->config('commerce_checkout_link.settings')->get();
    // The old default was to always use changed time.
    $use_changed_time = TRUE;
    if (isset($config['use_changed_timestamp'])) {
      $use_changed_time = (bool) $config['use_changed_timestamp'];
    }
    if (!hash_equals($hash, CheckoutLinkManager::generateHash($timestamp, $commerce_order, $use_changed_time))) {
      $logger->info('A user used a checkout link that had the wrong hash');
      // We might allow this, if the current setting is to allow changed time,
      // but the order link was created before the setting was changed.
      if ($use_changed_time) {
        throw new AccessDeniedHttpException('The hash provided did not match the expected hash');
      }
      $logger->info('Checking hash using strict changed timestamp comparison as well');
      if (!hash_equals($hash, CheckoutLinkManager::generateHash($timestamp, $commerce_order, TRUE))) {
        throw new AccessDeniedHttpException('The hash provided did not match the expected hash');
      }
    }
    // Always make sure there is no active cart for the user.
    $carts = $this->cartProvider->getCarts();
    foreach ($carts as $cart) {
      if ($cart->id() == $commerce_order->id()) {
        continue;
      }
      $cart->delete();
    }
    // Remove all session carts for anonymous users.
    if ($this->currentUser->isAnonymous()) {
      $ids = $this->cartSession->getCartIds();
      foreach ($ids as $id) {
        if ($id == $commerce_order->id()) {
          continue;
        }
        $this->cartSession->deleteCartId($id);
      }
    }
    try {
      $this->orderAssignment->assign($commerce_order, $this->entityTypeManager->getStorage('user')->load($this->currentUser->id()));
    }
    catch (\Throwable $e) {
      $logger->error('Caught an exception when trying to assign an order. Message was @msg and stack trace was @trace', [
        '@msg' => $e->getMessage(),
        '@trace' => $e->getTraceAsString(),
      ]);
      return [
        '#markup' => $this->t('There was an error claiming the order'),
      ];
    }
    if ($this->currentUser->isAnonymous()) {
      $this->cartSession->addCartId($commerce_order->id());
    }

    // Prepare default redirect URL.
    $url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $commerce_order->id(),
    ], ['absolute' => TRUE]);

    // Dispatch an event so that other modules can interact with order or change
    // the redirect URL.
    $event = new CheckoutLinkEvent($commerce_order, $url);
    $this->eventDispatcher->dispatch($event, CommerceCheckoutLinkEvents::CHECKOUT_LINK_REDIRECT);

    return new RedirectResponse($event->getUrl()->toString());
  }

}
