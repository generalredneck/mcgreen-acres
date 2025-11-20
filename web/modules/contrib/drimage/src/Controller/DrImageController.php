<?php

namespace Drupal\drimage\Controller;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\file\Entity\File;
use Drupal\image\Controller\ImageStyleDownloadController;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Simple extension over the default image download controller.
 *
 * We inherit from it so we have all functions and logic available. We just
 * override the way the image is generated to suit the needs of the dynamically
 * generated image styles.
 *
 * Images are scaled by default but cropping can be activated on the formatter
 * settings form.
 * When cropping is not activated a height of 0 is passed to the Controller.
 */
class DrImageController extends ImageStyleDownloadController {

  /**
   * Given a raw width and height: check if it adheres to the settings.
   *
   * @param int $width
   *   The raw requested width.
   * @param int $height
   *   The raw requested height.
   *
   * @return bool
   *   Indicates valid width/height against the settings.
   */
  public function checkRequestedDimensions(int $width, int $height): bool {
    // Check if the width is between the defined min/max settings.
    $drimage_config = $this->config('drimage.settings');
    if ($width > $drimage_config->get('downscale') || $width < $drimage_config->get('upscale')) {
      return FALSE;
    }

    // If the width is not at the maximum, check if it is at an exact threshold
    // multiplier, taking into account the minimum value.
    if ($width != $drimage_config->get('downscale')) {
      if (($width - $drimage_config->get('upscale')) % $drimage_config->get('threshold') != 0) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Generic function to get a standardized drimage id for the image style.
   *
   * @param int[] $requested_dimensions
   *   The calculated requested dimensions.
   * @param string|NULL $iwc_id
   *    Optional image_widget_crop crop type to use.
   *
   * @return string The id drimage should use for image_styles.
   */
  protected function getDrimageId(array $requested_dimensions, ?string $iwc_id): string {
    if ($iwc_id) {
      if ($this->moduleHandler->moduleExists('image_widget_crop')) {
        if (\Drupal::entityTypeManager()->getStorage('crop_type')->load($iwc_id)) {
          return 'drimage_' . $requested_dimensions[0] . '_' . $requested_dimensions[1] . '_' . $iwc_id;
        }
      }
    }

    if ($this->moduleHandler()->moduleExists('focal_point')) {
      return 'drimage_focal_' . $requested_dimensions[0] . '_' . $requested_dimensions[1];
    }

    return 'drimage_' . $requested_dimensions[0] . '_' . $requested_dimensions[1];
  }

  /**
   * Generic function to get a standardized drimage label for the image style.
   *
   * @param int[] $requested_dimensions
   *   The calculated requested dimensions.
   * @param string|NULL $iwc_id
   *    Optional image_widget_crop crop type to use.
   *
   * @return string The label drimage should use for image_styles.
   */
  protected function getDrimageLabel(array $requested_dimensions, ?string $iwc_id) {
    if ($iwc_id) {
      if ($this->moduleHandler()->moduleExists('image_widget_crop')) {
        if (\Drupal::entityTypeManager()->getStorage('crop_type')->load($iwc_id)) {
          return 'drimage_' . $requested_dimensions[0] . '_' . $requested_dimensions[1] . '_' . $iwc_id;
        }
      }
    }

    if ($this->moduleHandler()->moduleExists('focal_point')) {
      return 'drimage_focal_' . $requested_dimensions[0] . '_' . $requested_dimensions[1];
    }

    return 'drimage_' . $requested_dimensions[0] . '_' . $requested_dimensions[1];
  }

  /**
   * Try and find an image style that matches the requested dimensions.
   *
   * @param int[] $requested_dimensions
   *   The calculated requested dimensions.
   * @param string|NULL $iwc_id
   *    Optional image_widget_crop crop type to use.
   *
   * @return mixed
   *   A matching image style or NULL if none was found.
   */
  public function findImageStyle(array $requested_dimensions, ?string $iwc_id) {
    $focal_point = $this->moduleHandler()->moduleExists('focal_point');

    // Try and get an exact match:
    $name = $this->getDrimageId($requested_dimensions, $iwc_id);
    $image_style = ImageStyle::load($name);

    if ($image_style === NULL) {
      // If the image has a height we might be able to use an image style with a
      // very small distortion.
      if (isset($requested_dimensions[1]) && $requested_dimensions[1] > 0) {
        $styles = ImageStyle::loadMultiple();
        $current_ratio_distortion_diff = 360;
        foreach ($styles as $name => $style) {
          $drimage_config = $this->config('drimage.settings');
          // Calculate the dimensions from the style name.
          $translated_name = str_replace('drimage_', '', $name);
          if ($focal_point) {
            $translated_name = str_replace('focal_', '', $translated_name);
          }
          // Skip image styles without drimage_ prefix.
          if ($name == $translated_name) {
            continue;
          }
          $dimensions = explode('_', $translated_name);
          // Skip image styles that will only scale.
          if ($dimensions[1] <= 0) {
            continue;
          }

          if ($dimensions[0] == $requested_dimensions[0]) {
            // Find an image style with the least amount of distortion.
            $ratio_distortion = deg2rad($drimage_config->get('ratio_distortion') / 60);
            $ratio = $dimensions[0] / $dimensions[1];
            $requested_ratio = $requested_dimensions[0] / $requested_dimensions[1];
            $calculated_ratio_distortion_diff = abs(atan($ratio) - atan($requested_ratio));
            if ($calculated_ratio_distortion_diff <= $ratio_distortion
              && $calculated_ratio_distortion_diff < $current_ratio_distortion_diff) {
              $current_ratio_distortion_diff = $calculated_ratio_distortion_diff;
              $image_style = $styles[$name];
            }
          }
        }
      }
    }

    // No usable image style could be found, so we will have to create one.
    if ($image_style === NULL) {
      // When the site starts from a cold cache situation and a lot of requests
      // come in, the webserver might fail at this point, so try a few times.
      $counter = 0;
      while (empty($image_style) && $counter < 10) {
        usleep(rand(10000, 50000));
        $image_style = $this->createDrimageStyle($requested_dimensions, $iwc_id);
        $counter++;
      }
    }

    return $image_style;
  }

  /**
   * Create an image style from the requested dimensions.
   *
   * @param int[] $requested_dimensions
   *   The array containing the dimensions.
   * @param string|NULL $iwc_id
   *    Optional image_widget_crop crop type to use.
   *
   * @return \Drupal\image\ImageStyleInterface|bool
   *   The image style or FALSE in case something went wrong.
   */
  public function createDrimageStyle(array $requested_dimensions, ?string $iwc_id) {
    $name = $this->getDrimageId($requested_dimensions, $iwc_id);
    $label = $this->getDrimageLabel($requested_dimensions, $iwc_id);

    // When multiple images width the same dimension are requested in 1 page
    // we can sometimes trigger errors here. Image style can already be
    // created by another request that came in a few milliseconds before this
    // request. Catch that error and try and use the image style that was
    // already created.
    try {
      $style = ImageStyle::create(['name' => $name, 'label' => $label]);
      $drimage_config = $this->config('drimage.settings');

      // If webp is used, convert the image before doing anything else.
      if ($drimage_config->get('core_webp') === TRUE) {
        $convert_webp_effect_config = [
          'uuid' => NULL,
          'id' => 'image_convert',
          'weight' => 0,
          'data' => [
            'extension' => 'webp',
          ],
        ];

        $convert_webp_effect = \Drupal::service('plugin.manager.image.effect')
          ->createInstance(
            $convert_webp_effect_config['id'],
            $convert_webp_effect_config
          );
        $style->addImageEffect($convert_webp_effect->getConfiguration());
      }

      // When using image_widget_crop insert that here first after converting.
      if ($iwc_id) {
        if ($this->moduleHandler()->moduleExists('image_widget_crop')) {
          if (\Drupal::entityTypeManager()->getStorage('crop_type')->load($iwc_id)) {
            $iwc_configuration = [
              'uuid' => NULL,
              'id' => 'crop_crop',
              'weight' => 1,
              'data' => [
                'crop_type' => $iwc_id,
              ],
            ];

            // Add support for automated_crop module.
            if (!empty($drimage_config->get('automated_crop'))) {
              $iwc_configuration['data']['automatic_crop_provider'] = $drimage_config->get('automated_crop');
            }

            $effect = \Drupal::service('plugin.manager.image.effect')->createInstance($iwc_configuration['id'], $iwc_configuration);
            $style->addImageEffect($effect->getConfiguration());
          }
        }
      }

      $configuration = [
        'uuid' => NULL,
        'weight' => 2,
        'data' => [
          'upscale' => FALSE,
          'width' => NULL,
          'height' => NULL,
        ],
      ];
      $configuration['data']['width'] = $requested_dimensions[0];
      if ($requested_dimensions[1] > 0) {
        $configuration['data']['height'] = $requested_dimensions[1];
      }

      // Height is NULL by default, images are scaled.
      if ($configuration['data']['width'] == NULL || $configuration['data']['height'] == NULL) {
        $configuration['id'] = 'image_scale';
      }
      else {
        $configuration['id'] = 'image_scale_and_crop';

        // If focal point module is activated, use that image style instead.
        if (stripos($name, 'drimage_focal_') !== FALSE) {
          $configuration['id'] = 'focal_point_scale_and_crop';
        }
      }

      $effect = \Drupal::service('plugin.manager.image.effect')->createInstance($configuration['id'], $configuration);
      $style->addImageEffect($effect->getConfiguration());
      // Allow other modules to alter image style.
      $this->moduleHandler()->alter('drimage_image_style', $style);
      $style->save();
      $styles[$name] = $style;
      $image_style = $styles[$name];
    }
    catch (EntityStorageException $e) {
      // Wait a tiny little bit to make sure another request isn't still adding
      // effects to the image style.
      usleep(rand(10000, 50000));
      $image_style = ImageStyle::load($name);
    }
    catch (\Exception $e) {
      return NULL;
    }

    return $image_style;
  }

  /**
   * Deliver an image from the requested parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param int $width
   *    The requested width in pixels that came from the JS.
   * @param int $height
   *    The requested height in pixels that came from the JS.
   * @param int $fid
   *    The file id to render.
   * @param string $iwc_id
   *    The id for the image_widget crop type to use.
   * @param string $format
   *    The format to render the image in. Can be webp, jpg, png, ...
   *    Currently only webp is actually supported as an alternative.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the file is not found.
   */
  public function image(Request $request, int $width, int $height, int $fid, string $iwc_id, string $format) {
    // Bail out if the image is not valid.
    $file = File::load($fid);
    if (!$file) {
      throw new NotFoundHttpException($this->t('Error generating image, file not found.'));
    }
    $image = $this->imageFactory->get($file->getFileUri());
    if (!$image->isValid()) {
      throw new NotFoundHttpException($this->t('Error generating image, invalid file.'));
    }
    $drimage_config = $this->config('drimage.settings');

    // Set a NULL error_msg to prevent PHP notices further on.
    $error_msg = NULL;

    // The Javascript should have generated a nice size adhering to the
    // threshold and x/y up/down-scaling settings. Check if it actually did.
    // Return the fallback image if it didn't.
    if (!$this->checkRequestedDimensions($width, $height)) {
      $error_msg = $this->t('Error generating image, invalid dimensions.');
    }

    // Check the provided iwc crop type if given. We need a check for the "-"
    // value because non iwc handling provides "-" as default for this parameter
    // to indicate that we don't use iwc.
    if ($iwc_id === '-') {
      $iwc_id = NULL;
    }

    if ($iwc_id !== NULL) {
      // image_widget_crop styles should never have a height.
      $height = 0;
      if (!$this->moduleHandler()->moduleExists('image_widget_crop')) {
        $error_msg = $this->t('Image_widget_crop module is not active.');
      }
      else if (!$crop_type = \Drupal::entityTypeManager()->getStorage('crop_type')->load($iwc_id)) {
        $error_msg = $this->t('Image_widget_crop type not found.');
      }
    }

    // Try and find a matching image style.
    $requested_dimensions = [0 => $width, 1 => $height];
    $image_style = $this->findImageStyle($requested_dimensions, $iwc_id);
    if (empty($image_style)) {
      $error_msg = $this->t('Could not find matching image style.');
    }

    // Variable translation to make the original imageStyle deliver method work.
    $image_uri = explode('://', $file->getFileUri());
    $scheme = $image_uri[0];
    $request->query->set('file', $image_uri[1]);

    // Use the fallback image style if something went wrong.
    if (!empty($error_msg)) {
      $fallback_style = $drimage_config->get('fallback_style');
      if (!empty($fallback_style)) {
        $image_style = ImageStyle::load($fallback_style);
      }
    }

    if (!empty($image_style)) {
      // Because drimage does not use itok, we simulate it.
      if (!$this->config('image.settings')->get('allow_insecure_derivatives')) {
        $image_uri = $image_uri[0] . '://' . $image_uri[1];
        $request->query->set(IMAGE_DERIVATIVE_TOKEN, $image_style->getPathToken($image_uri));
      }

      // Uncomment to test the loading effect:
      //usleep(1000000);

      // Give the browser back an image in the format it requested.
      // This can be:
      //    - webp through the imageapi_optimize_webp module
      //    - or it will fall back to the default file in the filesystem for all other formats passed.
      // @see: \Drupal\drimage\PathProcessor\PathProcessorImageStyles::processInbound()
      // @todo: should probably override the deliver function to handle this properly.
      //
      // Added a looped try-catch construction to try and catch various deadlock issues.
      // These are hard te debug and it is not totally clear where they emerge from also.
      // 1 example: https://www.drupal.org/project/automated_crop/issues/3317597
      $attempts = 0;
      $error = TRUE;
      $response = NULL;
      while ($error && $attempts < 10) {
        $attempts++;
        try {
          $response = $this->deliver($request, $scheme, $image_style, $scheme);
          if ($format === 'webp' && ($drimage_config->get('core_webp') || $drimage_config->get('imageapi_optimize_webp'))) {
            $webp_image_derivative_uri = $image_style->buildUri($image_uri . '.webp');
            if (file_exists($webp_image_derivative_uri)) {
              $image = $this->imageFactory->get($webp_image_derivative_uri);
              $uri = $image->getSource();
              $headers = [
                'Content-Type' => 'image/webp',
                'Content-Length' => $image->getFileSize(),
              ];
              $response = new BinaryFileResponse($uri, 200, $headers, $scheme !== 'private');
            }
            else {
              // If the derivative does not exist, return a failed response.
              $response = new Response($this->t('Error loading webp variant for image.'), 500);
            }
          }

          $maxage = $drimage_config->get('cache_max_age');
          if ($maxage === 0) {
            $response->headers->set('Cache-Control', 'must-revalidate, no-cache, private');
          }
          elseif (is_int($maxage)) {
            $response->setMaxAge($maxage);
          }
          $error = FALSE;
        }
        catch (\Exception $e) {
          // Wait for a random bit of time (0,1-0,5s) to prevent concurrent failures from stacking again.
          usleep(rand(100000, 500000));
          $error = TRUE;
        }
      }

      if ($response) {
        return $response;
      }
    }

    return new Response($error_msg, 500);
  }

}
