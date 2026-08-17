<?php

namespace Drupal\jquery_downgrade\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config form for jQuery Downgrade settings.
 */
class JQueryDowngradeSettingsForm extends ConfigFormBase {

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Constructs a new JQueryDowngradeSettingsForm.
   *
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler service.
   */
  public function __construct(ThemeHandlerInterface $theme_handler) {
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('theme_handler'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['jquery_downgrade.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'jquery_downgrade_settings_form';
  }

  /**
   * Gets all available Views pages as selectable options.
   */
  protected function getAvailableViewsPages() {
    $views_options = [];
    $views = Views::getAllViews();

    foreach ($views as $view) {
      foreach ($view->get('display') as $display_id => $display) {
        if (!empty($display['display_options']['path'])) {
          $route_name = "view.{$view->id()}.$display_id";
          $views_options[$route_name] = "{$view->label()} - {$display['display_title']} ({$route_name})";
        }
      }
    }

    return $views_options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('jquery_downgrade.settings');
    $themes = $this->themeHandler->listInfo();
    $views_options = $this->getAvailableViewsPages();

    $theme_options = [];
    foreach ($themes as $theme) {
      $theme_options[$theme->getName()] = $theme->getName();
    }

    // Convert stored routes (dots to underscores)
    $stored_view_routes = array_map(fn($route) => str_replace('.', '__', $route), $config->get('view_routes') ?? []);

    // Convert available Views route keys to underscores (for form checkboxes)
    $views_options_transformed = [];
    foreach ($views_options as $route_name => $label) {
      $transformed_key = str_replace('.', '__', $route_name);
      $views_options_transformed[$transformed_key] = $label;
    }

    $form['node_ids'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Node IDs'),
      '#description' => $this->t('Enter the node IDs (one per line) where jQuery 3 should be loaded.'),
      '#default_value' => implode("\n", $config->get('node_ids') ?? []),
    ];

    $form['view_routes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('View Routes'),
      '#description' => $this->t('Select Views pages where jQuery 3 should be loaded.'),
      '#options' => $views_options_transformed, // Use transformed keys
      '#default_value' => $stored_view_routes, // Use transformed keys for default values
    ];

    $form['enable_theme_downgrade'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable jQuery downgrade for specific themes'),
      '#default_value' => $config->get('enable_theme_downgrade') ?? FALSE,
    ];

    $form['downgrade_themes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Themes that should use jQuery 3'),
      '#options' => $theme_options,
      '#default_value' => $config->get('downgrade_themes') ?? [],
      '#states' => [
        'visible' => [
          ':input[name="enable_theme_downgrade"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }



  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('jquery_downgrade.settings')
      ->set('node_ids', array_filter(array_map('trim', explode("\n", $form_state->getValue('node_ids')))))
      ->set('view_routes', array_map(fn($route) => str_replace('__', '.', $route), array_filter($form_state->getValue('view_routes')))) // Restore dots before saving
      ->set('enable_theme_downgrade', $form_state->getValue('enable_theme_downgrade'))
      ->set('downgrade_themes', array_filter($form_state->getValue('downgrade_themes')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

