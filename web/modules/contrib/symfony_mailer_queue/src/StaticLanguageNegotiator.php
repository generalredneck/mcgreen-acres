<?php

namespace Drupal\symfony_mailer_queue;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\LanguageNegotiator;

/**
 * Provides a static language negotiator.
 */
class StaticLanguageNegotiator extends LanguageNegotiator implements StaticLanguageNegotiatorInterface {

  /**
   * The static langcode.
   */
  protected ?string $langcode = NULL;

  /**
   * {@inheritdoc}
   */
  public function initializeType($type): array {

    $language = NULL;
    $available_languages = $this->languageManager->getLanguages();

    if ($this->langcode && isset($available_languages[$this->langcode])) {
      $language = $available_languages[$this->langcode];
    }
    else {
      $language = $this->languageManager->getDefaultLanguage();
    }

    return [static::METHOD_ID => $language];
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguage(?LanguageInterface $language = NULL): static {
    $this->langcode = $language?->getId() ??
      $this->languageManager->getDefaultLanguage()->getId();
    return $this;
  }

}
