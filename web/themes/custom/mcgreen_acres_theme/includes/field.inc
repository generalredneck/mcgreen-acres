<?php

    /**
     * Implements hook_theme_suggestions_field_alter().
     */
    function mcgreen_acres_theme_theme_suggestions_field_alter(array &$suggestions, array $variables) {
      $element = $variables['element'];

      // Check if the field is associated with an entity.
      if (isset($element['#entity_type']) && isset($element['#bundle']) && isset($element['#view_mode'])) {
        $entity_type = $element['#entity_type'];
        $bundle = $element['#bundle'];
        $view_mode = $element['#view_mode'];
        $field_name = $element['#field_name'];

        // Add suggestions based on entity type, bundle, view mode, and field name.
        // Example: field--node--field-image--article--teaser.html.twig
        $suggestions[] = 'field__' . $entity_type . '__' . str_replace('-', '_', $field_name) . '__' . $bundle . '__' . $view_mode ;
        $suggestions[] = 'field__' . $entity_type . '__' . str_replace('-', '_', $field_name) . '__' . $view_mode ;
      }
    }
