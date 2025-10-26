<?php

namespace Drupal\phone_number\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'phone_number' element.
 *
 * @WebformElement(
 *   id = "phone_number",
 *   label = @Translation("Phone Number"),
 *   description = @Translation("Provides a form element to display a phone number with country code and extension. Supports validation of international phone numbers."),
 *   category = @Translation("Composite elements"),
 *   composite = TRUE,
 * )
 */
class PhoneNumber extends WebformCompositeBase {

  /**
   * The Phone Number field utility.
   *
   * @var \Drupal\phone_number\PhoneNumberUtilInterface
   */
  protected $phoneNumberUtil;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->phoneNumberUtil = $container->get('phone_number.util');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    return parent::getDefaultProperties() + [
      'multiple' => FALSE,
      'multiple__header_label' => '',
      'default_country' => 'US',
      'allowed_countries' => NULL,
      'allowed_types' => NULL,
      'extension_field' => FALSE,
      'placeholder' => $this->t('Phone number'),
      'as_link' => FALSE,
      'country_selection' => 'flag',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCompositeElements() {
    $elements = [];
    $elements['value'] = [
      '#title' => $this->t('Value'),
      '#type' => 'textfield',
    ];
    $elements['country'] = [
      '#title' => $this->t('Country'),
      '#type' => 'textfield',
    ];
    $elements['local_number'] = [
      '#title' => $this->t('Local number'),
      '#type' => 'textfield',
    ];
    $elements['extension'] = [
      '#title' => $this->t('Extension'),
      '#type' => 'textfield',
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\phone_number\Plugin\Field\FieldType\PhoneNumberItem::schema
   */
  public function initializeCompositeElements(array &$element) {
    $element['#webform_composite_elements'] = [
      'value' => [
        '#title' => $this->t('Value'),
        '#type' => 'textfield',
        '#maxlength' => 19,
      ],
      'country' => [
        '#title' => $this->t('Country'),
        '#type' => 'textfield',
        '#maxlength' => 3,
      ],
      'local_number' => [
        '#title' => $this->t('Local number'),
        '#type' => 'textfield',
        '#maxlength' => 15,
      ],
      'extension' => [
        '#title' => $this->t('Extension'),
        '#type' => 'textfield',
        '#maxlength' => 40,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $settings['country_selection'] = [
      '#type' => 'select',
      '#title' => $this->t('Country selection'),
      '#options' => [
        'code' => $this->t('Two-letter ISO country code'),
        'flag' => $this->t('Flag'),
      ],
      '#default_value' => 'flag',
      '#required' => TRUE,
    ];

    $settings['default_country'] = [
      '#type' => 'select',
      '#title' => $this->t('Default country'),
      '#options' => $this->phoneNumberUtil->getCountryOptions(NULL, TRUE),
      '#description' => $this->t('Default country for phone number input.'),
      '#required' => TRUE,
    ];

    $settings['allowed_countries'] = [
      '#type' => 'select',
      '#title' => $this->t('Allowed countries'),
      '#options' => $this->phoneNumberUtil->getCountryOptions(NULL, TRUE),
      '#description' => $this->t('Allowed counties for the phone number. If none selected, then all are allowed.'),
      '#multiple' => TRUE,
      '#attached' => ['library' => ['phone_number/element']],
    ];

    $settings['allowed_types'] = [
      '#type' => 'select',
      '#title' => $this->t('Allowed types'),
      '#options' => $this->phoneNumberUtil->getTypeOptions(),
      '#description' => $this->t('Restrict entry to certain types of phone numbers. If none are selected, then all types are allowed.  A description of each type can be found <a href="@url" target="_blank">here</a>.', [
        '@url' => 'https://github.com/giggsey/libphonenumber-for-php/blob/master/src/PhoneNumberType.php',
      ]),
      '#multiple' => TRUE,
    ];

    $settings['extension_field'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable <em>Extension</em> field'),
      '#description' => $this->t('Collect extension along with the phone number.'),
    ];

    $form['composite'] = $settings + $form['composite'];

    $form['display']['as_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show as TEL link'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $default_country = $form_state->getValue('default_country');
    $allowed_countries = $form_state->getValue('allowed_countries');
    if (!empty($allowed_countries) && !in_array($default_country, $allowed_countries)) {
      $form_state->setErrorByName('phone_number][default_country', $this->t('Default country is not in one of the allowed countries.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultValue(array &$element) {
    parent::setDefaultValue($element);

    $settings = [
      'default_country' => !empty($element['#default_country']) ? $element['#default_country'] : 'US',
    ];

    // When multiple values are allow on the Webform element, the
    // '#default_value' key can already be present with either the default
    // value key as string or NULL.
    // These situations arise because of
    // \Drupal\webform\Element\WebformMultiple::setElementDefaultValue
    // and
    // \Drupal\webform\Element\WebformMultiple::setElementRowDefaultValueRecursive.
    //
    // Use the given default value.
    if (isset($element['#default_value']) && is_string($element['#default_value'])) {

      $element['#default_value'] = [
        'country' => $element['#default_value'],
      ];
    }
    // Force set with settings, as NULL + an array doesn't result in an array.
    // If the value is an empty array, this is fine too.
    elseif (empty($element['#default_value'])) {

      $element['#default_value'] = [
        'country' => $settings['default_country'],
      ];
    }
    // The code doesn't seem to end up here, but keep this in case
    // '#default_value' isset, is not a string and assume it will be an array.
    else {

      $element += [
        '#default_value' => [
          'country' => $settings['default_country'],
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    parent::prepare($element, $webform_submission);

    $element['#description'] = $this->renderer->render($element['#description']);

    $element += [
      '#phone_number' => [
        'allowed_countries' => $element['#allowed_countries'] ?? NULL,
        'allowed_types' => $element['#allowed_types'] ?? NULL,
        'extension_field' => $element['#extension_field'] ?? FALSE,
        'country_selection' => $element['#country_selection'] ?? 'flag',
        'placeholder' => $element['#placeholder'] ?? '',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formatHtmlItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->getValue($element, $webform_submission, $options);

    if (empty($value['value'])) {
      return '';
    }

    $format = $this->getItemFormat($element);
    $phoneDisplayFormat = NULL;
    switch ($format) {
      case 'phone_number_international':
        $phoneDisplayFormat = PhoneNumberFormat::INTERNATIONAL;
        break;

      case 'phone_number_local':
        $phoneDisplayFormat = PhoneNumberFormat::NATIONAL;
        break;
    }
    $as_link = !empty($element['#as_link']);

    $extension = NULL;
    if (!empty($element['#extension_field']) && isset($value['extension'])) {
      $extension = $value['extension'];
    }

    if ($phone_number = $this->phoneNumberUtil->getPhoneNumber($value['value'], NULL, $extension)) {
      if (!empty($as_link)) {
        $element = [
          '#type' => 'link',
          '#title' => $this->phoneNumberUtil->libUtil()->format($phone_number, $phoneDisplayFormat),
          '#url' => Url::fromUri($this->phoneNumberUtil->getRfc3966Uri($phone_number)),
        ];
      }
      else {
        $element = [
          '#plain_text' => $this->phoneNumberUtil->libUtil()->format($phone_number, $phoneDisplayFormat),
        ];
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemDefaultFormat() {
    return 'phone_number_international';
  }

  /**
   * {@inheritdoc}
   */
  public function getItemFormats() {
    return parent::getItemFormats() + [
      'phone_number_international' => $this->t('International'),
      'phone_number_local' => $this->t('Local Number'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTestValues(array $element, WebformInterface $webform, array $options = []) {
    $values = [];

    $allowed_countries = $this->getElementProperty($element, 'allowed_countries');
    if (empty($allowed_countries)) {
      $allowed_countries = array_keys($this->phoneNumberUtil->getCountryOptions());
    }

    $allowed_types = $this->getElementProperty($element, 'allowed_types');
    if (empty($allowed_types)) {
      $allowed_types = [
        PhoneNumberType::FIXED_LINE,
        PhoneNumberType::MOBILE,
        PhoneNumberType::FIXED_LINE_OR_MOBILE,
      ];
    }

    $use_ext = $this->getElementProperty($element, 'extension_field');

    for ($i = 1; $i <= 5; $i++) {
      $country_index = array_rand($allowed_countries);
      $country = $allowed_countries[$country_index];
      $type_index = array_rand($allowed_types);
      $type = $allowed_types[$type_index];

      /** @var \libphonenumber\PhoneNumber $number */
      $number = $this->phoneNumberUtil->libUtil()->getExampleNumberForType($country, $type);
      if (is_null($number)) {
        continue;
      }

      $value = [
        'value' => $this->phoneNumberUtil->getCallableNumber($number),
      ];
      if ($use_ext) {
        $value['extension'] = random_int(1, 99);
      }
      $values[] = $value;
    }

    return $values;
  }

}
