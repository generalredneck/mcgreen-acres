<?php

namespace Drupal\custom_commerce_simplenews_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_simplenews_checkout\Plugin\Commerce\CheckoutPane\SimplenewsSubscription as CommerceSimplenewsSubscription;
use Drupal\simplenews\Entity\Subscriber;

/**
 * Provides the subscription information pane.
 *
 * @CommerceCheckoutPane(
 *   id = "simplenews_subscription",
 *   label = @Translation("Simplenews subscription"),
 *   default_step = "summary",
 *   wrapper_element = "container",
 * )
 */
class SimplenewsSubscription extends CommerceSimplenewsSubscription {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'label' => 'Keep me updated with news and offers.',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['subscribe'] = [
      '#type' => 'checkbox',
      '#title' => $this->configuration['label'],
      '#default_value' => TRUE,
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if (empty($values['subscribe'])) {
      return;
    }

    $email = $this->order->getEmail();
    if (empty($email)) {
      return;
    }

    $subscriber = Subscriber::loadByMail($email);
    if (!$subscriber) {
      $subscriber = Subscriber::create(['mail' => $email]);
      $subscriber->save();
    }

    foreach (array_keys($this->getNewslettersOptions()) as $newsletter_id) {
      $subscriber->subscribe($newsletter_id);
    }
    $subscriber->save();
  }

}
