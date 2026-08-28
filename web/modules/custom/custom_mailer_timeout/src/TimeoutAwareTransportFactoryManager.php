<?php

namespace Drupal\custom_mailer_timeout;

use Drupal\symfony_mailer\TransportFactoryManagerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

/**
 * Decorates the transport factory manager with a timeout-aware ESMTP factory.
 *
 * Swaps in the timeout-aware factory in place of Symfony's default one.
 */
class TimeoutAwareTransportFactoryManager implements TransportFactoryManagerInterface {

  public function __construct(protected TransportFactoryManagerInterface $inner) {}

  /**
   * {@inheritdoc}
   */
  public function addFactory(TransportFactoryInterface $factory) {
    $this->inner->addFactory($factory);
  }

  /**
   * {@inheritdoc}
   */
  public function getFactories() {
    $factories = array_filter($this->inner->getFactories(), function ($factory) {
      return !($factory instanceof EsmtpTransportFactory);
    });
    $factories[] = new TimeoutAwareEsmtpTransportFactory();

    return $factories;
  }

}
