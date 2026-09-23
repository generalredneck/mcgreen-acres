<?php

namespace Drupal\term_condition\Controller;

use Drupal\system\Controller\EntityAutocompleteController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Route controller for term_condition's entity autocomplete form element.
 *
 * Reuses the core autocomplete request handling, only swapping in the
 * term_condition.autocomplete_matcher service so results include the
 * vocabulary name of matched taxonomy terms.
 */
class TermAutocompleteController extends EntityAutocompleteController {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('term_condition.autocomplete_matcher'),
      $container->get('keyvalue')->get('entity_autocomplete')
    );
  }

}
