<?php

namespace Drupal\commerce_checkout_link\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Url;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Defines the order event.
 *
 * @see \Drupal\commerce_checkout_link\Event\CommerceCheckoutLinkEvents
 */
class CheckoutLinkEvent extends Event {

  /**
   * The order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The redirect URL.
   *
   * @var \Drupal\Core\Url
   */
  protected $url;

  /**
   * Constructs a new OrderEvent.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\Core\Url $url
   *   The redirect URL.
   */
  public function __construct(OrderInterface $order, Url $url) {
    $this->order = $order;
    $this->url = $url;
  }

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   Gets the order.
   */
  public function getOrder() : OrderInterface {
    return $this->order;
  }

  /**
   * The redirect URL.
   *
   * @return \Drupal\Core\Url
   *   The URL.
   */
  public function getUrl() : Url {
    return $this->url;
  }

  /**
   * Sets the redirect url.
   *
   * @param \Drupal\Core\Url $url
   *   The url.
   *
   * @return $this
   */
  public function setUrl(Url $url) : self {
    $this->url = $url;
    return $this;
  }

}
