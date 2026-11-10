<?php

namespace Drupal\symfony_mailer_queue;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\LanguageNegotiatorInterface;

/**
 * Provides an interface for the static language negotiator.
 */
interface StaticLanguageNegotiatorInterface extends LanguageNegotiatorInterface {

  /**
   * Sets the static language.
   */
  public function setLanguage(?LanguageInterface $language = NULL): static;

}
