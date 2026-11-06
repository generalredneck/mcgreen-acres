<?php

namespace Drupal\ms_clarity\Helpers;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PrivateKey;

/**
 * Microsoft Clarity Account class.
 */
class MicrosoftClarityAccounts {


  /**
   * The loaded config for the MS Module.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * The Microsoft Clarity Accounts storage.
   *
   * @var string
   */
  private $accounts;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\PrivateKey $private_key
   *   The private key service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, PrivateKey $private_key) {
    $this->config = $config_factory->get('ms_clarity.settings');
    $this->accounts = $this->config->get('account');
    if ($this->accounts == NULL) {
      $this->accounts = "";
    }
  }

  /**
   * Return Project ID.
   */
  public function getProjectId() {
    return $this->accounts;
  }

}
