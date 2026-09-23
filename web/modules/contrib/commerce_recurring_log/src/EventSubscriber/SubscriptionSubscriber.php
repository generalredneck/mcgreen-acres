<?php

namespace Drupal\commerce_recurring_log\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SubscriptionSubscriber implements EventSubscriberInterface {

  /**
   * The log storage.
   *
   * @var \Drupal\commerce_log\LogStorageInterface
   */
  protected $logStorage;

  /**
   * Constructs a new SubscriptionSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->logStorage = $entity_type_manager->getStorage('commerce_log');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'commerce_subscription.post_transition' => [
        ['onPostTransition', 100],
      ],
    ];
  }

  /**
   * Creates a log on subscription state update.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onPostTransition(WorkflowTransitionEvent $event) {
    $transition = $event->getTransition();
    /** @var \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription */
    $subscription = $event->getEntity();
    $original_state_id = $subscription->getState()->getOriginalId();
    $original_state = $event->getWorkflow()->getState($original_state_id);

    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();
    $user_name = $current_user->getDisplayName();

    if (!$current_user->isAnonymous()) {
      $user_name = $current_user->getEmail();
    }

    // If the user is anonymous and this is the first state transition,
    // the user is the customer who placed the initial order.
    if ($current_user->isAnonymous() && $original_state_id == 'pending') {
      if ($initial_order = $subscription->getInitialOrder()) {
        if ($email = $initial_order->getEmail()) {
          // Default to the email address as the user name.
          $user_name = $email;
        }
      }
    }

    try {
      $log = $this->logStorage->generate($subscription, 'subscription_state_updated', [
        'transition_label' => $transition->getLabel(),
        'from_state' => $original_state ? $original_state->getLabel() : $original_state_id,
        'to_state' => $subscription->getState()->getLabel(),
        'user_name' => $user_name,
      ]);
      $log->set('uid', $uid);
      $log->save();
    } catch (\Drupal\Core\Entity\EntityStorageException $e) {
      \Drupal::logger('commerce_recurring_log')->error('Failed to log subscription state update: @message', ['@message' => $e->getMessage()]);
    }
  }

}