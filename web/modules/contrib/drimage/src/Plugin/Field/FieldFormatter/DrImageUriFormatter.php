<?php

namespace Drupal\drimage\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\crop\Entity\CropType;

/**
 * Plugin implementation of the 'dynamic responsive image' formatter.
 *
 * @FieldFormatter(
 *   id = "drimage_uri",
 *   label = @Translation("Dynamic Responsive Image"),
 *   field_types = {
 *     "uri",
 *     "file_uri"
 *   }
 * )
 */
class DrImageUriFormatter extends DrImageFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // @todo: can it not be multiple?
    $file = $items->getEntity();
    $config = \Drupal::configFactory()->get('drimage.settings');

    $url = Url::fromUri('internal:/')->toString();
    if (substr($url, -1) === '/') {
      $url = substr($url, 0, -1);
    }

    // Get the image loading attribute, revert to legacy if none was found.
    $image_loading = $this->getSetting('image_loading')['attribute'];
    if ($config->get('legacy_lazyload')) {
      $image_loading = 'legacy';
    }

    foreach ($elements as $delta => $element) {
      $elements[$delta]['#item_attributes'] = new Attribute();
      $elements[$delta]['#item_attributes']['class'] = ['drimage'];
      $elements[$delta]['#theme'] = 'drimage_formatter';
      $elements[$delta]['#data'] = [
        'fid' => $file->id(),
        // Add the original filename for SEO purposes.
        'filename' => pathinfo($file->getFileUri())['basename'],
        // Add needed data for calculations.
        'threshold' => $config->get('threshold'),
        'upscale' => $config->get('upscale'),
        'downscale' => $config->get('downscale'),
        'multiplier' => $config->get('multiplier'),
        'core_webp' => $config->get('core_webp'),
        'imageapi_optimize_webp' => $config->get('imageapi_optimize_webp'),
        'lazy_offset' => $config->get('lazy_offset'),
        'subdir' => $url,
        'lazyload' => $image_loading,
      ];

      // Get original image data. (non cropped, non processed) This is useful when
      // implementing lightbox-style plugins that show the original image.
      $elements[$delta]['#width'] = $file->getMetaData('width');
      $elements[$delta]['#height'] = $file->getMetaData('height');
      $elements[$delta]['#core_webp'] = $config->get('core_webp');
      $elements[$delta]['#imageapi_optimize_webp'] = $config->get('imageapi_optimize_webp');
      $elements[$delta]['#alt'] = $file->getMetaData('alt');
      $elements[$delta]['#title'] = $file->getMetaData('title');
      $elements[$delta]['#data']['original_width'] = $file->getMetaData('width');
      $elements[$delta]['#data']['original_height'] = $file->getMetaData('height');
      $elements[$delta]['#data']['original_source'] = \Drupal::service('file_url_generator')
        ->generateString($file->getFileUri());

      // Add image_handling and specific data for the type of handling.
      $elements[$delta]['#data']['image_handling'] = $this->getSetting('image_handling');
      switch ($elements[$delta]['#data']['image_handling']) {
        case 'background':
          $elements[$delta]['#data']['background'] = [
            'attachment' => $this->getSetting('background')['attachment'],
            'position' => $this->getSetting('background')['position'],
            'size' => $this->getSetting('background')['size'],
          ];
          break;

        case 'aspect_ratio':
          $elements[$delta]['#data']['aspect_ratio'] = [
            'width' => $this->getSetting('aspect_ratio')['width'],
            'height' => $this->getSetting('aspect_ratio')['height'],
          ];
          $elements[$delta]['#width'] = $this->getSetting('aspect_ratio')['width'];
          $elements[$delta]['#height'] = $this->getSetting('aspect_ratio')['height'];
          break;

        case 'iwc':
          // Override the width / height to match the aspect ratio.
          $crop_style = \Drupal::entityTypeManager()
            ->getStorage('crop_type')
            ->load($this->getSetting('iwc')['image_style']);
          if ($crop_style instanceof CropType && $aspect_ratio = $crop_style->getAspectRatio()) {
            [$width, $height] = explode(':', $aspect_ratio);
            $elements[$delta]['#width'] = $width;
            $elements[$delta]['#height'] = $height;
          }
          $elements[$delta]['#data']['iwc'] = [
            'image_style' => $this->getSetting('iwc')['image_style'],
          ];
          break;

        case 'scale':
        default:
          // Nothing extra needed here.
          break;
      }

      // Unset the fallback image.
      unset($elements[$delta]['#image']);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareView(array $entities_items) {}

}
