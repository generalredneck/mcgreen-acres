<?php

namespace Drupal\mcgreen_acres_store\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\State\StateInterface;
use Drupal\mcgreen_acres_store\Form\FarmstandSettingsForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Explains farm stand pickup, reflecting the site-wide open/closed state.
 *
 * @Block(
 *   id = "farmstand_info_block",
 *   admin_label = @Translation("Farm Stand Info")
 * )
 */
class FarmstandInfoBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $closed = (bool) $this->state->get(FarmstandSettingsForm::STATE_KEY, FALSE);

    if ($closed) {
      $reason = trim((string) $this->state->get(FarmstandSettingsForm::REASON_STATE_KEY, ''));
      $message = $reason !== ''
        ? $this->t('The farm stand is currently closed due to @reason. Products are available via pickup at the farm at <strong>2414 Westmoreland Rd. Red Oak, TX 75154</strong> via appointment.', ['@reason' => $reason])
        : $this->t('The farm stand is currently closed. Products are available via pickup at the farm at <strong>2414 Westmoreland Rd. Red Oak, TX 75154</strong> via appointment.');
      $alert_class = 'alert-danger';
    }
    else {
      $message = $this->t('Merchandise available from the farm stand are free to take upon purchase as Self Serve.<br>Other products are available via pickup at the farm at <strong>2414 Westmoreland Rd. Red Oak, TX 75154</strong> via appointment.');
      $alert_class = 'alert-info';
    }

    return [
      '#type' => 'markup',
      '#markup' => Markup::create('<div class="alert ' . $alert_class . '"><p>' . $message . '</p></div>'),
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
