<?php

namespace Drupal\drd_agent\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure drd-agent settings for this site.
 */
class Settings extends FormBase {

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drd_agent_settings_form';
  }

  /**
   * @param \Drupal\Core\State\StateInterface $state
   *   The state factory.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $state = \Drupal::state();

    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => t('Debug mode'),
      '#default_value' => $state->get('drd_agent.debug_mode', FALSE),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit')
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $debug_mode = $form_state->getValue('debug_mode');
    $this->state->set('drd_agent.debug_mode', $debug_mode);
    $this->messenger()->addStatus($this->t('Settings saved'));
  }

}
