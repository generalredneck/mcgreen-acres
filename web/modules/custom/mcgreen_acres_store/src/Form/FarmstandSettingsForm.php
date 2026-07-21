<?php

namespace Drupal\mcgreen_acres_store\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
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

  const REASON_STATE_KEY = 'mcgreen_acres_store.farmstand_closure_reason';

  /**
   * Cache tag invalidated whenever the flag or reason changes.
   *
   * Bubbled onto the product teaser/full render arrays (see the
   * mcgreen_acres_store_preprocess_commerce_product__* hooks) so cached
   * markup - including Views row caches on the product catalog - is
   * evicted immediately instead of lingering until an unrelated cache
   * clear (e.g. `drush cr`).
   */
  const CACHE_TAG = 'mcgreen_acres_store:farmstand_availability';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  public function __construct(StateInterface $state, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->state = $state;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('cache_tags.invalidator')
    );
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

    $form['closure_reason'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Closure reason'),
      '#description' => $this->t('Optional. Shown to customers as "…currently closed due to [reason]." Leave blank to just say "…currently closed."'),
      '#default_value' => $this->state->get(self::REASON_STATE_KEY, ''),
      '#maxlength' => 255,
      '#states' => [
        'visible' => [
          ':input[name="farmstand_closed"]' => ['checked' => TRUE],
        ],
      ],
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
    $this->state->set(self::REASON_STATE_KEY, trim((string) $form_state->getValue('closure_reason')));
    $this->cacheTagsInvalidator->invalidateTags([self::CACHE_TAG]);

    $this->messenger()->addMessage($closed
      ? $this->t('Farm stand marked closed. All products now show as order-ahead only.')
      : $this->t('Farm stand marked open. Products follow their individual availability settings again.'));
  }

}
