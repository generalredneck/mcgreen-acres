<?php

namespace Drupal\entity_embed\Plugin\entity_embed\EntityEmbedDisplay;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\TypedDataManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;

/**
 * Entity Embed Display reusing image field formatters.
 *
 * @see \Drupal\entity_embed\EntityEmbedDisplay\EntityEmbedDisplayInterface
 *
 * @EntityEmbedDisplay(
 *   id = "image",
 *   label = @Translation("Image"),
 *   entity_types = {"file"},
 *   deriver = "Drupal\entity_embed\Plugin\Derivative\FieldFormatterDeriver",
 *   field_type = "image",
 *   provider = "image"
 * )
 */
class ImageFieldFormatter extends FileFieldFormatter {

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->imageFactory = $container->get('image.factory');
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValue() {
    $value = parent::getFieldValue();
    // File field support descriptions, but images do not.
    unset($value['description']);
    $value += array_intersect_key($this->getAttributeValues(), ['alt' => '', 'title' => '']);
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account = NULL) {
    return parent::access($account)->andIf($this->isValidImage());
  }

  /**
   * Checks if the image is valid.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Returns the access result.
   */
  protected function isValidImage() {
    // If entity type is not file we have to return early to prevent fatal in
    // the condition above. Access should already be forbidden at this point,
    // which means this won't have any effect.
    // @see EntityEmbedDisplayBase::access()
    if ($this->getEntityTypeFromContext() != 'file') {
      return AccessResult::forbidden();
    }
    $access = AccessResult::allowed();

    // @todo needs cacheability metadata for getEntityFromContext.
    // @see \Drupal\entity_embed\EntityEmbedDisplay\EntityEmbedDisplayBase::getEntityFromContext()
    /** @var \Drupal\file\FileInterface $entity */
    if ($entity = $this->getEntityFromContext()) {
      // Loading large files is slow, make sure it is an image mime type before
      // doing that.
      list($type,) = explode('/', $entity->getMimeType(), 2);
      $is_valid_image = FALSE;
      if ($type == 'image') {
        $is_valid_image = $this->imageFactory->get($entity->getFileUri())->isValid();
        if (!$is_valid_image) {
          $this->messenger->addMessage($this->t('The selected image "@image" is invalid.', ['@image' => $entity->label()]), 'error');
        }
      }
      $access = AccessResult::allowedIf($type == 'image' && $is_valid_image)
        // See the above @todo, this is the best we can do for now.
        ->addCacheableDependency($entity);
    }

    return $access;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $entity_element = $form_state->get('entity_element');
    // File field support descriptions, but images do not.
    unset($form['description']);

    // Ensure that the 'Link image to: Content' setting is not available.
    if ($this->getDerivativeId() == 'image' || $this->getDerivativeId() == 'responsive_image') {
      unset($form['image_link']['#options']['content']);
      if (empty($entity_element['data-entity-embed-display-settings'])) {
        $entity_element['data-entity-embed-display-settings'] = [];
      }
      elseif (is_string($entity_element['data-entity-embed-display-settings'])) {
        $entity_element['data-entity-embed-display-settings'] = Json::decode($entity_element['data-entity-embed-display-settings']) ?: [];
      }
      $form['link_url'] = [
        '#title' => t('Link to'),
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#attributes' => [
          'data-autocomplete-first-character-blacklist' => '/#?'
        ],
        '#element_validate' => [[get_called_class(), 'validateUriElement']],
        '#process_default_value' => FALSE,
        '#description' => $this->t('Start typing the title of a piece of content to select it. You can also enter an internal path such as %add-node or an external URL such as %url. Enter %front to link to the front page.', ['%front' => '<front>', '%add-node' => '/node/add', '%url' => 'http://example.com']),
        '#default_value' => isset($entity_element['data-entity-embed-display-settings']['link_url']) ? $this->getUriAsDisplayableString($entity_element['data-entity-embed-display-settings']['link_url']) : '',
        '#maxlength' => 2048,
      ];
      $form['link_url_target'] = [
        '#title' => t('Open in a new window?'),
        '#type' => 'checkbox',
        '#default_value' => isset($entity_element['data-entity-embed-display-settings']['link_url_target']) ? Html::decodeEntities($entity_element['data-entity-embed-display-settings']['link_url_target']) : '',
      ];
    }


    // The alt attribute is *required*, but we allow users to opt-in to empty
    // alt attributes for the very rare edge cases where that is valid by
    // specifying two double quotes as the alternative text in the dialog.
    // However, that *is* stored as an empty alt attribute, so if we're editing
    // an existing image (which means the src attribute is set) and its alt
    // attribute is empty, then we show that as two double quotes in the dialog.
    // @see https://www.drupal.org/node/2307647
    // Alt attribute behavior is taken from the Core image dialog to ensure a
    // consistent UX across various forms.
    // @see Drupal\editor\Form\EditorImageDialog::buildForm()
    $alt = $this->getAttributeValue('alt', '');
    if ($alt === '') {
      // Do not change empty alt text to two double quotes if the previously
      // used Entity Embed Display plugin was not 'image:image'. That means that
      // some other plugin was used so if this image formatter is selected at a
      // later stage, then this should be treated as a new edit. We show two
      // double quotes in place of empty alt text only if that was filled
      // intentionally by the user.
      if (!empty($entity_element) && $entity_element['data-entity-embed-display'] == 'image:image') {
        $alt = MediaImageDecorator::EMPTY_STRING;
      }
    }

    // Add support for editing the alternate and title text attributes.
    $form['alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alternate text'),
      '#default_value' => $alt,
      '#description' => $this->t('This text will be used by screen readers, search engines, or when the image cannot be loaded.'),
      '#parents' => ['attributes', 'alt'],
      '#required' => TRUE,
      '#required_error' => $this->t('Alternative text is required.<br />(Only in rare cases should this be left empty. To create empty alternative text, enter <code>""</code> — two double quotes without any content).'),
      '#maxlength' => 512,
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->getAttributeValue('title', ''),
      '#description' => t('The title is used as a tool tip when the user hovers the mouse over the image.'),
      '#parents' => ['attributes', 'title'],
      '#maxlength' => 1024,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // When the alt attribute is set to two double quotes, transform it to the
    // empty string: two double quotes signify "empty alt attribute". See above.
    if (trim($form_state->getValue(['attributes', 'alt'])) === MediaImageDecorator::EMPTY_STRING) {
      $form_state->setValue(['attributes', 'alt'], '');
    }
  }

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * The following two forms of URIs are transformed:
   * - 'entity:' URIs: to entity autocomplete ("label (entity id)") strings;
   * - 'internal:' URIs: the scheme is stripped.
   *
   * This method is the inverse of ::getUserEnteredStringAsUri().
   *
   * @param string $uri
   *   The URI to get the displayable string for.
   *
   * @return string
   *
   * @see static::getUserEnteredStringAsUri()
   */
  protected function getUriAsDisplayableString($uri) {
    $uri = Html::decodeEntities($uri);
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity') {
      list($entity_type, $entity_id) = explode('/', substr($uri, 7), 2);
      // Show the 'entity:' URI as the entity autocomplete would.
      // @todo Support entity types other than 'node'. Will be fixed in
      //    https://www.drupal.org/node/2423093.
      if ($entity_type == 'node' && $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }

    return $displayable_string;
  }

  /**
   * Gets the user-entered string as a URI.
   *
   * The following two forms of input are mapped to URIs:
   * - entity autocomplete ("label (entity id)") strings: to 'entity:' URIs;
   * - strings without a detectable scheme: to 'internal:' URIs.
   *
   * This method is the inverse of ::getUriAsDisplayableString().
   *
   * @param string $string
  +   *   The user-entered string.
  +   *
  +   * @return string
  +   *   The URI, if a non-empty $uri was passed.
  +   *
  +   * @see static::getUriAsDisplayableString()
  +   */
  protected static function getUserEnteredStringAsUri($string) {
    // By default, assume the entered string is an URI.
    $uri = $string;

    // Detect entity autocomplete string, map to 'entity:' URI.
    $entity_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($string);
    if ($entity_id !== NULL) {
      // @todo Support entity types other than 'node'. Will be fixed in
      //    https://www.drupal.org/node/2423093.
      $uri = 'entity:node/' . $entity_id;
    }
    // Detect a schemeless string, map to 'internal:' URI.
    elseif (!empty($string) && parse_url($string, PHP_URL_SCHEME) === NULL) {
      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      // - '<front>' -> '/'
      // - '<front>#foo' -> '/#foo'
      if (strpos($string, '<front>') === 0) {
        $string = '/' . substr($string, strlen('<front>'));
      }
      $uri = 'internal:' . $string;
    }

    return $uri;
  }

  /**
   * Form element validation handler for the 'uri' element.
   *
   * Disallows saving inaccessible or untrusted URLs.
   */
  public static function validateUriElement($element, FormStateInterface $form_state, $form) {
    $uri = static::getUserEnteredStringAsUri($element['#value']);
    $form_state->setValueForElement($element, $uri);

    // If getUserEnteredStringAsUri() mapped the entered value to a 'internal:'
    // URI , ensure the raw value begins with '/', '?' or '#'.
    // @todo '<front>' is valid input for BC reasons, may be removed by
    //   https://www.drupal.org/node/2421941
    if (parse_url($uri, PHP_URL_SCHEME) === 'internal' && !in_array($element['#value'][0], ['/', '?', '#'], TRUE) && substr($element['#value'], 0, 7) !== '<front>') {
      $form_state->setError($element, t('Manually entered paths should start with /, ? or #.'));
      return;
    }

    if (!empty($uri) && str_starts_with($uri, 'http')  && !preg_match('/^http[s]?:\/\/[\w\W]+/', $uri)) {
      $form_state->setError($element, t('Manually entered paths should start with http:// or https://'));
      return;
    }
  }
}
