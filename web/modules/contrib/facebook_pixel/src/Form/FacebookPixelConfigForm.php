<?php

namespace Drupal\facebook_pixel\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Facebook Pixel config.
 */
class FacebookPixelConfigForm extends ConfigFormBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a FacebookPixelConfigForm object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'facebook_pixel.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'facebook_pixel_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('facebook_pixel.settings');
    $form['facebook_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facebook Pixel ID'),
      '#description' => $this->t('The Facebook Pixel ID provided in the process of creating the tracking pixel.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('facebook_id'),
    ];

    // Visibility settings.
    $form['tracking_scope'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Tracking scope'),
      '#attached' => [
        'library' => [
          'facebook_pixel/facebook_pixel.admin',
        ],
      ],
    ];

    // Page specific visibility configurations.
    $visibility_request_path_pages = $config->get('visibility.request_path_pages');

    $form['tracking']['page_visibility_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Pages'),
      '#group' => 'tracking_scope',
    ];

    $description = $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are %blog for the blog page and %blog-wildcard for every personal blog. %front is the front page.", [
      '%blog' => '/blog',
      '%blog-wildcard' => '/blog/*',
      '%front' => '<front>',
    ]);

    $form['tracking']['page_visibility_settings']['facebook_pixel_visibility_request_path_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Add tracking to specific pages'),
      '#options' => [
        'all_pages' => $this->t('Every page except the listed pages'),
        'listed_pages' => $this->t('The listed pages only'),
      ],
      '#default_value' => $config->get('visibility.request_path_mode'),
    ];
    $form['tracking']['page_visibility_settings']['facebook_pixel_visibility_request_path_pages'] = [
      '#type' => 'textarea',
      '#title' => 'Pages',
      '#title_display' => 'invisible',
      '#default_value' => !empty($visibility_request_path_pages) ? $visibility_request_path_pages : '',
      '#description' => $description,
      '#rows' => 10,
    ];

    // Render the role overview.
    $visibility_user_role_roles = $config->get('visibility.user_role_roles');

    $form['tracking']['role_visibility_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Roles'),
      '#group' => 'tracking_scope',
    ];

    $form['tracking']['role_visibility_settings']['facebook_pixel_visibility_user_role_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Add tracking for specific roles'),
      '#options' => [
        'all_roles' => $this->t('Add to every role except the selected ones'),
        'listed_roles' => $this->t('Add to the selected roles only'),
      ],
      '#default_value' => $config->get('visibility.user_role_mode'),
    ];
    $form['tracking']['role_visibility_settings']['facebook_pixel_visibility_user_role_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#default_value' => !empty($visibility_user_role_roles) ? $visibility_user_role_roles : [],
      '#options' => array_map(function (RoleInterface $role) {
        return Html::escape($role->label());
      }, Role::loadMultiple()),
      '#description' => $this->t('If none of the roles are selected, all users will be tracked. If a user has any of the roles checked, that user will be tracked (or excluded, depending on the setting above).'),
    ];

    // Privacy specific configurations.
    $form['tracking']['privacy'] = [
      '#type' => 'details',
      '#title' => $this->t('Privacy'),
      '#group' => 'tracking_scope',
    ];
    $form['tracking']['privacy']['facebook_pixel_privacy_donottrack'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Universal web tracking opt-out'),
      '#description' => $this->t('If enabled, if a user has <a href="@donottrack">Do-Not-Track</a> enabled in the browser, the Facebook Pixel module will not execute the tracking code on your site. Compliance with Do Not Track could be purely voluntary, enforced by industry self-regulation, or mandated by state or federal law. Please accept your visitors privacy. If they have opt-out from tracking and advertising, you should accept their personal decision.', ['@donottrack' => 'https://www.eff.org/issues/do-not-track']),
      '#default_value' => $config->get('privacy.donottrack'),
    ];
    $form['tracking']['privacy']['facebook_pixel_fb_disable_advanced'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Advanced fb-disable user opt-out'),
      '#description' => $this->t('If enabled, for enhanced privacy if Facebook Pixel user opt-out code "<i>window[\'fb-disable\']</i>" is true, the Facebook pixel module will not execute the Facebook Pixel tracking code on your site. Furthermore provides the global JavaScript function "fbOptout()" to set an opt-out cookie if called.'),
      '#default_value' => $config->get('privacy.fb_disable_advanced'),
    ];
    $form['tracking']['privacy']['facebook_pixel_eu_cookie_compliance'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('EU Cookie Compliance integration'),
      '#description' => $this->t('If enabled, the Facebook Pixel module will not track users as long as we do not have their consent. This option is designed to work with the module <a href="@eu_cookie_compliance">Eu Cookie Compliance</a>. Technically it checks for Drupal.eu_cookie_compliance.hasAgreed(). <strong>Important:</strong> Set "Script scope" to "Header" in the EU Cookie Compliance settings for this to work.', ['@eu_cookie_compliance' => 'https://www.drupal.org/project/eu_cookie_compliance']),
      '#default_value' => $this->moduleHandler->moduleExists('eu_cookie_compliance') ? $config->get('privacy.eu_cookie_compliance') : 0,
      '#disabled' => !$this->moduleHandler->moduleExists('eu_cookie_compliance'),
    ];
    $form['tracking']['privacy']['facebook_pixel_disable_noscript_img'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable noscript fallback image, which does not respect any of these privacy features.'),
      '#default_value' => $config->get('privacy.disable_noscript_img'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $form_state->setValue('facebook_pixel_visibility_request_path_pages', trim($form_state->getValue('facebook_pixel_visibility_request_path_pages')));
    $form_state->setValue('facebook_pixel_visibility_user_role_roles', array_filter($form_state->getValue('facebook_pixel_visibility_user_role_roles')));
    // Verify that every path is prefixed with a slash, but don't check PHP
    // code snippets and do not check for slashes if no paths configured.
    if (!empty($form_state->getValue('facebook_pixel_visibility_request_path_pages'))) {
      $pages = preg_split('/(\r\n?|\n)/', $form_state->getValue('facebook_pixel_visibility_request_path_pages'));
      foreach ($pages as $page) {
        if (strpos($page, '/') !== 0 && $page !== '<front>') {
          $form_state->setErrorByName('facebook_pixel_visibility_request_path_pages', $this->t('Path "@page" not prefixed with slash.', ['@page' => $page]));
          // Drupal forms show one error only.
          break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('facebook_pixel.settings')
      ->set('facebook_id', $form_state->getValue('facebook_id'))
      ->set('visibility.request_path_mode', $form_state->getValue('facebook_pixel_visibility_request_path_mode'))
      ->set('visibility.request_path_pages', $form_state->getValue('facebook_pixel_visibility_request_path_pages'))
      ->set('visibility.user_role_mode', $form_state->getValue('facebook_pixel_visibility_user_role_mode'))
      ->set('visibility.user_role_roles', $form_state->getValue('facebook_pixel_visibility_user_role_roles'))
      ->set('visibility.user_role_roles', $form_state->getValue('facebook_pixel_visibility_user_role_roles'))
      ->set('privacy.donottrack', $form_state->getValue('facebook_pixel_privacy_donottrack'))
      ->set('privacy.fb_disable_advanced', $form_state->getValue('facebook_pixel_fb_disable_advanced'))
      ->set('privacy.eu_cookie_compliance', $form_state->getValue('facebook_pixel_eu_cookie_compliance'))
      ->set('privacy.disable_noscript_img', $form_state->getValue('facebook_pixel_disable_noscript_img'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
