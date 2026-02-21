<?php

namespace Drupal\commerce_invoice\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Url;

/**
 * Defines an element for language configuration.
 *
 * @FormElement("commerce_invoice_language_configuration")
 */
class LanguageConfiguration extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processLanguageConfiguration'],
      ],
    ];
  }

  /**
   * Process handler for the commerce_invoice_language_configuration element.
   *
   * @return array
   *   The processed element.
   */
  public static function processLanguageConfiguration(&$element, FormStateInterface $form_state, &$form) {
    $options = $element['#options'] ?? [];
    // Avoid validation failure since we are moving the '#options' key in the
    // nested 'language' select element.
    unset($element['#options']);
    /** @var \Drupal\language\Entity\ContentLanguageSettings $default_config */
    $default_config = $element['#default_value'];
    $element['langcode'] = [
      '#type' => 'select',
      '#title' => t('Default language'),
      '#options' => $options + static::getDefaultOptions(),
      '#description' => t('Explanation of the language options is found on the <a href=":languages_list_page">languages list page</a>.', [':languages_list_page' => Url::fromRoute('entity.configurable_language.collection')->toString()]),
      '#default_value' => ($default_config != NULL) ? $default_config->getDefaultLangcode() : LanguageInterface::LANGCODE_SITE_DEFAULT,
    ];
    $element['generate_translations'] = [
      '#type' => 'checkbox',
      '#title' => t('Generate translations for each of the available languages (on invoice generation)'),
      '#default_value' => ($default_config != NULL) ? $default_config->getThirdPartySetting('commerce_invoice', 'generate_translations', FALSE) : FALSE,
    ];

    // Add the entity type and bundle information to the form if they are set.
    // They will be used, in the submit handler, to generate the names of the
    // configuration entities that will store the settings and are a way to uniquely
    // identify the entity.
    $language = $form_state->get('language') ?: [];
    $language += [
      $element['#name'] => [
        'entity_type' => $element['#entity_information']['entity_type'],
        'bundle' => $element['#entity_information']['bundle'],
      ],
    ];
    $form_state->set('language', $language);
    $form['actions']['submit']['#submit'][] = 'commerce_invoice_language_configuration_element_submit';

    return $element;
  }

  /**
   * Returns the default options for the language configuration form element.
   *
   * @return array
   *   An array containing the default options.
   */
  protected static function getDefaultOptions() {
    $language_options = [
      LanguageInterface::LANGCODE_SITE_DEFAULT => t("Site's default language (@language)", ['@language' => static::languageManager()->getDefaultLanguage()->getName()]),
    ];

    $languages = static::languageManager()->getLanguages(LanguageInterface::STATE_ALL);
    foreach ($languages as $langcode => $language) {
      if (in_array($langcode, [LanguageInterface::LANGCODE_NOT_SPECIFIED, LanguageInterface::LANGCODE_NOT_APPLICABLE])) {
        continue;
      }
      $language_options[$langcode] = $language->isLocked() ? t('- @name -', ['@name' => $language->getName()]) : $language->getName();
    }

    return $language_options;
  }

  /**
   * Wraps the language manager.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   */
  protected static function languageManager() {
    return \Drupal::languageManager();
  }

}
