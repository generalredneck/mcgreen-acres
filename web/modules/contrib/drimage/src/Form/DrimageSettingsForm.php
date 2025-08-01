<?php

namespace Drupal\drimage\Form;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\ImageToolkit\ImageToolkitManager;
use Drupal\crop\Events\AutomaticCropProviders;
use Drupal\crop\Events\Events;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DrimageSettingsForm.
 *
 * @package Drupal\drimage\Form
 */
class DrimageSettingsForm extends ConfigFormBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The image toolkit manager.
   *
   * @var \Drupal\Core\ImageToolkit\ImageToolkitManager
   */
  protected $imageToolkitManager;

  /**
   * Constructs a DrimageSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\ImageToolkit\ImageToolkitManager $image_toolkit_manager
   *   The image toolkit manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    DateFormatterInterface $date_formatter,
    ModuleHandlerInterface $module_handler,
    ImageToolkitManager $image_toolkit_manager
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->dateFormatter = $date_formatter;
    $this->moduleHandler = $module_handler;
    $this->imageToolkitManager = $image_toolkit_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('date.formatter'),
      $container->get('module_handler'),
      $container->get('image.toolkit.manager')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'drimage_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'drimage.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Provide some feedback on the insecure derivative implementation.
    if (!$this->config('image.settings')->get('allow_insecure_derivatives')) {
      \Drupal::messenger()->addMessage($this->t('The "allow_insecure_derivatives" settings is disabled, but drimage will bypass this.'), 'warning');
    }

    $form['threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum difference per image style'),
      '#default_value' => $this->config('drimage.settings')->get('threshold'),
      '#description' => $this->t('A minimum amount 2 image styles have to differ before a new image style is being created. For cropping styles, the biggest dimension is used. This feature will limit your disk space usage, but the quality of images might be less good since the browser has to scale them.'),
      '#min' => 1,
      '#max' => 500,
      '#step' => 1,
      '#field_suffix' => ' ' . $this->t('pixels'),
    ];

    $form['ratio_distortion'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum allowed ratio distortion'),
      '#default_value' => $this->config('drimage.settings')->get('ratio_distortion'),
      '#description' => $this->t('How much ratio distortion is allowed when trying to reuse image styles that crop images. The aspect ratio of the generated images will be distorted by the browser to keep the exact aspect ratio your CSS rules require. A minimum of 30 minutes is required to allow for small rounding errors.'),
      '#min' => 1,
      '#max' => 3600,
      '#step' => 1,
      '#field_suffix' => ' ' . $this->t("minutes. (1° = 60')"),
    ];

    $form['downscale'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum image style width'),
      '#default_value' => $this->config('drimage.settings')->get('downscale'),
      '#description' => $this->t("The maximum width for the biggest image style. Anything bigger will be scaled down to this size unless aspect ratio's and other min/max settings force it otherwise."),
      '#min' => 1,
      '#max' => 10000,
      '#step' => 1,
      '#field_suffix' => ' ' . $this->t('pixels'),
    ];

    $form['upscale'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum image style width'),
      '#default_value' => $this->config('drimage.settings')->get('upscale'),
      '#description' => $this->t("The minimal width for the smallest image style. Anything smaller will be scaled up to this size unless aspect ratio's and other min/max settings force it otherwise."),
      '#min' => 1,
      '#max' => 500,
      '#step' => 1,
      '#field_suffix' => ' ' . $this->t('pixels'),
    ];

    $form['multiplier'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable device pixel ratio detection'),
      '#default_value' => $this->config('drimage.settings')->get('multiplier'),
      '#description' => $this->t('Will produce higher quality images on screens that have more physical pixels then logical pixels.'),
    ];

    $image_toolkit = $this->imageToolkitManager->getDefaultToolkit();
    $allow_core_webp = FALSE;
    $imageapi_optimize_webp_check = FALSE;
    $core_webp_option_enabled = $this->config('drimage.settings')->get('core_webp');
    $module_handler = \Drupal::service('module_handler');
    if ($module_handler->moduleExists('imageapi_optimize_webp')) {
      if ($imageapi_optimize_settings = \Drupal::config('imageapi_optimize.settings')) {
        if (!empty($imageapi_optimize_settings->get('default_pipeline'))) {
          $imageapi_optimize_webp_check = TRUE;
        }
      }
      if ($core_webp_option_enabled === TRUE) {
        // Disable core WebP when the image optimize WebP module gets enabled.
        $this->config('drimage.settings')->set('core_webp', FALSE)
          ->save();
        $core_webp_option_enabled = FALSE;
      }
    } elseif (in_array('webp', $image_toolkit->getSupportedExtensions())) {
      $allow_core_webp = TRUE;
    } elseif ($core_webp_option_enabled === TRUE) {
      // Disable core WebP if Webp support is removed from the image toolkit.
      $this->config('drimage.settings')->set('core_webp', FALSE)
        ->save();
      $core_webp_option_enabled = FALSE;
    }

    $form['core_webp'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable core webp support'),
      '#default_value' => $core_webp_option_enabled,
      '#description' => $this->t('Core webp support is only available when the image optimize webp module is disabled and current image toolkit supports WEBP images.'),
      '#disabled' => !$allow_core_webp,
    ];

    if ($image_toolkit->getPluginId() === 'gd') {
        $form['core_webp']['#description'] .= ' ' . $this->t('Visit the <a href="@statusReportUrl">status report</a> to check if your current GD version supports webp.', ['@statusReportUrl' => '/admin/reports/status/php#module_gd']);
    }

    $form['imageapi_optimize_webp'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable ImageAPI Optimize WebP support'),
      '#default_value' => $this->config('drimage.settings')->get('imageapi_optimize_webp'),
      '#description' => $this->t('This options is only available if <a href="https://www.drupal.org/project/imageapi_optimize_webp">ImageAPI Optimize WebP</a> module is installed, the core Webp option is disabled and a "Sitewide default pipeline" is set up.'),
      '#disabled' => (!$imageapi_optimize_webp_check || $core_webp_option_enabled === TRUE),
    ];

    $automated_crop_check = FALSE;
    $automated_crop_providers = [];
    $module_handler = \Drupal::service('module_handler');
    if ($module_handler->moduleExists('image_widget_crop') && $module_handler->moduleExists('automated_crop')) {
      $automated_crop_check = TRUE;
      $event = new AutomaticCropProviders();
      \Drupal::service('event_dispatcher')->dispatch($event, Events::AUTOMATIC_CROP_PROVIDERS);
      $automated_crop_providers = $event->getProviders();
    }
    $form['automated_crop'] = [
      '#type' => 'select',
      '#title' => $this->t('Enable automated_crop support'),
      '#options' => $automated_crop_providers,
      '#empty_option' => $this->t('- Select a Provider -'),
      '#default_value' => $this->config('drimage.settings')->get('automated_crop'),
      '#description' => $this->t('This options is only available if <a href="https://www.drupal.org/project/image_widget_crop">image_widget_crop</a> and <a href="https://www.drupal.org/project/automated_crop">automated_crop</a> are installed.'),
      '#disabled' => !$automated_crop_check,
    ];

    $form['lazy_offset'] = [
      '#type' => 'number',
      '#title' => $this->t('Lazyloader offeset'),
      '#default_value' => $this->config('drimage.settings')->get('lazy_offset'),
      '#description' => $this->t("Images are always lazy loaded once they are in the browser's canvas. This offset value loads them x amount of pixels before they are visible."),
      '#min' => 0,
      '#max' => 5000,
      '#step' => 1,
      '#field_suffix' => ' ' . $this->t('pixels'),
    ];

    $options = [];
    $styles = ImageStyle::loadMultiple();
    foreach ($styles as $style) {
      $id = $style->id();
      if (!stristr($id, 'drimage_')) {
        $options[$id] = $style->get('label');
      }
    }
    $form['fallback_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Fallback Image Style'),
      '#default_value' => $this->config('drimage.settings')->get('fallback_style'),
      '#description' => $this->t('If drimage cannot find an image style fallback to using this image style instead of generating an error and not showing an image at all.'),
      '#empty_option' => $this->t('None (original image)'),
      '#options' => $options,
    ];

    $period = [0, 60, 180, 300, 600, 900, 1800, 2700, 3600, 10800, 21600, 32400, 43200, 86400, 2 * 604800, 31536000];
    $this->moduleHandler->alter('drimage_proxy_cache_periods', $period);
    $period = array_map([$this->dateFormatter, 'formatInterval'], array_combine($period, $period));
    $period[0] = '<' . t('no caching') . '>';
    $form['cache_max_age'] = [
      '#type' => 'select',
      '#title' => t('Cache maximum age'),
      '#default_value' => $this->config('drimage.settings')->get('cache_max_age'),
      '#options' => $period,
      '#description' => t('The maximum time an image derivative can be cached. This should be disabled if you configured your server to serve existing image derivatives without bootstrapping Drupal (in case of Apache servers, using the htaccess.prepend.txt file provided by this module). In that case, caching headers will be set by the server instead.'),
    ];

    // @deprecated: only show if it was enabled explicitly.
    if ($this->config('drimage.settings')->get('legacy_lazyload')) {
      $form['legacy_lazyload'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use drimage JS lazyloader'),
        '#default_value' => $this->config('drimage.settings')->get('legacy_lazyload'),
        '#description' => $this->t('<strong>DEPRECATED!</strong> You should NOT use this option except for legacy/compatibility reasons. This option is only enabled for projects setup before the HTML lazyloading was implemented. Consider disabling this falling back to HTML lazyloading in your image formatters.'),
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('drimage.settings')
      ->set('threshold', $form_state->getValue('threshold'))
      ->set('ratio_distortion', $form_state->getValue('ratio_distortion'))
      ->set('upscale', $form_state->getValue('upscale'))
      ->set('downscale', $form_state->getValue('downscale'))
      ->set('multiplier', $form_state->getValue('multiplier'))
      ->set('core_webp', $form_state->getValue('core_webp'))
      ->set('imageapi_optimize_webp', $form_state->getValue('imageapi_optimize_webp'))
      ->set('automated_crop', $form_state->getValue('automated_crop'))
      ->set('lazy_offset', $form_state->getValue('lazy_offset'))
      ->set('fallback_style', $form_state->getValue('fallback_style'))
      ->set('cache_max_age', $form_state->getValue('cache_max_age'))
      ->set('legacy_lazyload', $form_state->getValue('legacy_lazyload'))
      ->save();
    \Drupal::messenger()->addMessage($this->t('Drimage Settings have been successfully saved.'));
  }

}
