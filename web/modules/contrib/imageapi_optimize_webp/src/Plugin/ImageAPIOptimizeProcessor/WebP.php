<?php

namespace Drupal\imageapi_optimize_webp\Plugin\ImageAPIOptimizeProcessor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\imageapi_optimize\ConfigurableImageAPIOptimizeProcessorBase;

/**
 * @ImageAPIOptimizeProcessor(
 *   id = "imageapi_optimize_webp",
 *   label = @Translation("WebP Deriver"),
 *   description = @Translation("Clone image to WebP")
 * )
 */
class WebP extends ConfigurableImageAPIOptimizeProcessorBase {

  /**
   * {@inheritdoc}
   */
  public function applyToImage($image_uri) {
    $source_image = $this->imageFactory->get($image_uri, 'gd');
    $file_type = pathinfo($image_uri, PATHINFO_EXTENSION);
    if ($file_type == 'webp' &&  $source_image->getToolkit()->getMimeType() != 'image/webp') {
      imagewebp($source_image->getToolkit()->getImage(), $image_uri, $this->configuration['quality']);
      // Fix issue where sometimes image fails to generate.
      if (filesize($image_uri) % 2 == 1) {
        file_put_contents($image_uri, "\0", FILE_APPEND);
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'quality' => 75,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // @todo: Add ability to pick which image types allow derivatives.
    $form['quality'] = [
      '#type' => 'number',
      '#title' => $this->t('Image quality'),
      '#description' => $this->t('Specify the image quality.'),
      '#default_value' => $this->configuration['quality'],
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 100,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['quality'] = $form_state->getValue('quality');
  }

}
