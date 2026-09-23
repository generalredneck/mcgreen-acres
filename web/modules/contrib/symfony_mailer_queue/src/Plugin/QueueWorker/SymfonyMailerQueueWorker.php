<?php

namespace Drupal\symfony_mailer_queue\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer_queue\Event\EmailSendFailureEvent;
use Drupal\symfony_mailer_queue\Event\EmailSendRequeueEvent;
use Drupal\symfony_mailer_queue\QueueableEmailInterface;
use Drupal\symfony_mailer_queue\StaticLanguageNegotiatorInterface;
use Drupal\symfony_mailer_queue\SymfonyMailerQueueItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Sends emails from queue and retries previous failures.
 *
 * @QueueWorker(
 *   id = \Drupal\symfony_mailer_queue\Plugin\QueueWorker\SymfonyMailerQueueWorker::QUEUE_NAME,
 *   title = @Translation("Sends emails and retries failures"),
 *   cron = {"time" = 60}
 * )
 */
class SymfonyMailerQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The name of the queue.
   */
  public const QUEUE_NAME = 'symfony_mailer_queue';

  /**
   * The config factory service.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The email factory.
   */
  protected EmailFactoryInterface $emailFactory;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The expirable key-value storage.
   */
  protected KeyValueExpirableFactoryInterface $keyValue;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The static language negotiator or NULL if the language module is disabled.
   */
  protected ?StaticLanguageNegotiatorInterface $languageNegotiator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = new static(...$defaults);
    $instance->configFactory = $container->get('config.factory');
    $instance->emailFactory = $container->get('email_factory');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->keyValue = $container->get('keyvalue.expirable');
    $instance->languageManager = $container->get('language_manager');
    // @phpstan-ignore-next-line Service defined in service provider.
    $instance->languageNegotiator = $container->get('symfony_mailer_queue.static_language_negotiator', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item): void {

    // Only process Symfony mailer queue items.
    if (!$item instanceof SymfonyMailerQueueItem) {
      return;
    }

    // Restore the language context that was active when the email was
    // originally queued.
    if (
      ($current_langcode = $item->langcode ?? NULL) &&
      $this->languageManager instanceof ConfigurableLanguageManagerInterface &&
      $this->languageNegotiator instanceof StaticLanguageNegotiatorInterface &&
      $language = $this->languageManager->getLanguage($current_langcode)
    ) {
      $this->languageNegotiator->setLanguage($language);
      $this->languageManager->setNegotiator($this->languageNegotiator);
      $this->languageManager->reset();
      $this->languageManager->setConfigOverrideLanguage($language);
    }

    // Reinitialize the email using data from the queue item. If a related
    // config entity is present, initialize it as an entity email; otherwise,
    // initialize it as a typed email.
    if (isset($item->entity)) {
      $email = $this->emailFactory->newEntityEmail(
        $item->entity,
        $item->subType,
        ...$item->params,
      );
    }
    else {
      $email = $this->emailFactory->newTypedEmail(
        $item->type,
        $item->subType,
        ...$item->params,
      );
    }

    if (!$email instanceof QueueableEmailInterface) {
      throw new \LogicException('Attempted to process a non-queueable email.');
    }

    // Reassign properties to the email, including variables, addresses,
    // sender, and others. These may have originally been set during business
    // logic execution. This reinstatement is necessary due to the Symfony
    // Mailer email object's overloaded responsibilities and its lack of easy
    // serialization. We also account for legacy queue items that may be
    // missing some properties.
    isset($item->variables) && $email->setVariables($item->variables);
    isset($item->inner) && $email->setInner($item->inner);
    isset($item->addresses) && $email->setAddresses($item->addresses);
    isset($item->sender) && $email->setSender($item->sender);
    isset($item->subject) && $email->setSubject($item->subject);
    isset($item->subjectReplace) && $email->setSubjectReplace($item->subjectReplace);
    isset($item->body) && $email->setBody($item->body);
    isset($item->theme) && $email->setTheme($item->theme);
    isset($item->transportDsn) && $email->setTransportDsn($item->transportDsn);

    // Attempt to send the email. Considered done when successfully delivered.
    $email->markInQueue();
    // @phpstan-ignore-next-line Dependent typing is insufficient.
    if ($email->send() ?? FALSE) {
      $send_wait_time = (int) ($item->config['send_wait_time'] ?? 0);
      sleep($send_wait_time);
      return;
    }

    // Retrieve and increase number of attempts when email delivery failed.
    $collection = $this->keyValue->get(static::QUEUE_NAME);
    $fingerprint = md5(serialize($item));
    $attempts = $collection->get($fingerprint, 0);
    $attempts++;

    // Fail ultimately when attempts are exhausted. Dispatch email send failure
    // event to allow other modules to react.
    $maximum_attempts = (int) ($item->config['maximum_attempts'] ?? 5);
    if ($attempts > $maximum_attempts) {
      $event = new EmailSendFailureEvent($item);
      $this->eventDispatcher->dispatch($event);
      return;
    }

    // Store attempts in the key-value storage keyed by an item fingerprint and
    // requeue item. Dispatch email send requeue event to allow other modules
    // to react. If the immediate requeue behavior is selected, release the
    // item. If the the queue is suspended the item will be released. The failed
    // item and other items are processed in a future queue run. Otherwise,
    // requeue the item with configured delay.
    $collection->setWithExpire($fingerprint, $attempts, 86400);
    $event = new EmailSendRequeueEvent($item);
    $this->eventDispatcher->dispatch($event);
    $queue_behavior = $item->config['queue_behavior'] ?? '';
    if ($queue_behavior === 'requeue') {
      throw new RequeueException();
    }
    if ($queue_behavior === 'suspend') {
      throw new SuspendQueueException();
    }
    $requeue_delay = (int) ($item->config['requeue_delay'] ?? 0);
    throw new DelayedRequeueException($requeue_delay);
  }

}
