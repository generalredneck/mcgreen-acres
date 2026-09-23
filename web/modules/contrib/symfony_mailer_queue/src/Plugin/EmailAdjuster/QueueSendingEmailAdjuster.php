<?php

namespace Drupal\symfony_mailer_queue\Plugin\EmailAdjuster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;
use Drupal\symfony_mailer_queue\Plugin\QueueWorker\SymfonyMailerQueueWorker;
use Drupal\symfony_mailer_queue\QueueableEmailInterface;
use Drupal\symfony_mailer_queue\SymfonyMailerQueueItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an email adjuster to queue sending of emails.
 *
 * @EmailAdjuster(
 *   id = "queue_sending",
 *   label = @Translation("Queue sending"),
 *   description = @Translation("Queues email instead of immediately sending it."),
 *   weight = 0,
 * )
 */
class QueueSendingEmailAdjuster extends EmailAdjusterBase implements ContainerFactoryPluginInterface {

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The queue factory.
   */
  protected QueueFactory $queueFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = new static(...$defaults);
    $instance->languageManager = $container->get('language_manager');
    $instance->queueFactory = $container->get('queue');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {

    if (!$email instanceof QueueableEmailInterface) {
      throw new \LogicException('Attempted to queue a non-queueable email.');
    }

    if (!$email->isInQueue()) {

      $queue = $this->queueFactory->get(SymfonyMailerQueueWorker::QUEUE_NAME, TRUE);

      // The email will be sent by the queue worker, which typically runs in an
      // environment where the default site language is used. To ensure the
      // email is delivered in the correct language, we explicitly pass the
      // current language here.
      $current_langcode = $this->languageManager->getCurrentLanguage()->getId();

      $item = new SymfonyMailerQueueItem(
        $email->getType(),
        $email->getSubType(),
        $email->getOriginalParams(),
        $email->getVariables(),
        $email->getInner(),
        $email->getAddresses(),
        $email->getSender(),
        $email->getSubject(),
        $email->getSubjectReplace(),
        $email->getBody(),
        $email->getTheme(),
        $email->getTransportDsn(),
        $email->getEntity(),
        $current_langcode,
        $this->configuration,
      );

      $queue->createItem($item);

      throw new SkipMailException('The email was queued for sending');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {

    $form['description'] = [
      '#markup' => '<p>' . $this->t('Queues email instead of immediately sending it.') . '</p>',
    ];

    $form['queue_behavior'] = [
      '#type' => 'select',
      '#title' => $this->t('Queue Behavior'),
      '#options' => [
        'delayed' => $this->t('Delayed requeue'),
        'requeue' => $this->t('Immediate requeue'),
        'suspend' => $this->t('Suspend queue'),
      ],
      '#default_value' => $this->configuration['queue_behavior'] ?? 'delayed',
      '#description' => $this->t('When email processing fails, items that are immediately requeued become available for repeated processing right away. If requeuing is delayed, the item will only be available after the specified delay or once its lease time expires. While not all queue systems support delays, the Drupal database queue does. When using cron to process the queue, proper garbage collection to release items has then to be configured. For queues that do not support delays, a default lease time of one minute applies. Additionally, the email queue can be suspended, which requeues the failed item and delays the processing of other items until the next scheduled run.'),
    ];

    $form['requeue_delay'] = [
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#title' => $this->t('Requeue delay'),
      '#default_value' => $this->configuration['requeue_delay'] ?? 60,
      '#field_suffix' => $this->t('seconds'),
      '#description' => $this->t('Specifies the wait time in seconds before retrying a failed email.'),
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[data-drupal-selector="edit-config-queue-sending-queue-behavior"]' => [
            'value' => 'delayed',
          ],
        ],
        'required' => [
          ':input[data-drupal-selector="edit-config-queue-sending-queue-behavior"]' => [
            'value' => 'delayed',
          ],
        ],
      ],
    ];

    $form['maximum_attempts'] = [
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#title' => $this->t('Maximum attempts'),
      '#default_value' => $this->configuration['maximum_attempts'] ?? 5,
      '#description' => $this->t('Specifies the number of retry attempts for sending an email before the queue item is suspended.'),
      '#required' => TRUE,
    ];

    $form['send_wait_time'] = [
      '#type' => 'number',
      '#step' => 1,
      '#min' => 0,
      '#title' => $this->t('Wait time per item'),
      '#description' => $this->t('Specifies the wait time in seconds between processing each queue item.'),
      '#field_suffix' => $this->t('seconds'),
      '#default_value' => $this->configuration['send_wait_time'] ?? 0,
      '#required' => TRUE,
    ];

    return $form;
  }

}
