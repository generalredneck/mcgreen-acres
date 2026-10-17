<?php

namespace Drupal\commerce_email\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A queue worker for sending emails.
 *
 * @QueueWorker(
 *  id = "commerce_email_queue",
 *  title = @Translation("Commerce Email Queue"),
 *  cron = {"time" = 60}
 * )
 */
class EmailQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The email sender.
   *
   * @var \Drupal\commerce_email\EmailSenderInterface
   */
  protected $emailSender;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->emailSender = $container->get('commerce_email.email_sender');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\commerce_email\Entity\EmailInterface $email */
    $email = $this->entityTypeManager->getStorage('commerce_email')->load($data['email_id']);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($data['entity_type_id'])->load($data['entity_id']);

    $related_entities = [];
    if (!empty($data['related_entities'])) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface[] $related_entities */
      foreach ($data['related_entities'] as $related_entity_type_id => $related_entity_id) {
        $related_entities[] = $this->entityTypeManager->getStorage($related_entity_type_id)->load($related_entity_id);
      }
    }

    if ($email && $entity) {
      $this->emailSender->send($email, $entity, $related_entities);
    }
  }

}
