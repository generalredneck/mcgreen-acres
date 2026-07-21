<?php

namespace Drupal\mcgreen_acres_store\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Site-wide "is the farm stand open" toggle.
 *
 * Backed by State rather than Config: this is operational state that
 * changes independently of any code deploy (e.g. an unplanned closure),
 * and the site's config/sync directory is reset by `drush cim` on every
 * deploy, which would silently reopen the farm stand on the next release.
 */
class FarmstandSettingsForm extends FormBase {

  const STATE_KEY = 'mcgreen_acres_store.farmstand_closed';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('state'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mcgreen_acres_store_farmstand_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['farmstand_closed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Farm stand is temporarily closed'),
      '#description' => $this->t('While checked, every product site-wide is treated as order-ahead/appointment-only, regardless of its individual "Available at the Farm Stand" setting. Uncheck to reopen.'),
      '#default_value' => $this->state->get(self::STATE_KEY, FALSE),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $closed = (bool) $form_state->getValue('farmstand_closed');
    $this->state->set(self::STATE_KEY, $closed);

    $this->messenger()->addMessage($closed
      ? $this->t('Farm stand marked closed. All products now show as order-ahead only.')
      : $this->t('Farm stand marked open. Products follow their individual availability settings again.'));
  }

}
