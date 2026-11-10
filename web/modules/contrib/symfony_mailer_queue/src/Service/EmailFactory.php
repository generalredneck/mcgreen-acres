<?php

namespace Drupal\symfony_mailer_queue\Service;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\symfony_mailer\EmailFactory as SymfonyMailerEmailFactory;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer_queue\QueueableEmail;

/**
 * Provides a factory for creating queueable email objects.
 */
class EmailFactory extends SymfonyMailerEmailFactory {

  /**
   * {@inheritdoc}
   */
  public function newTypedEmail(string $type, string $sub_type, ...$params): EmailInterface {
    // @phpstan-ignore-next-line Following parent implementation.
    $email = QueueableEmail::create(\Drupal::getContainer(), $type, $sub_type); // phpcs:ignore
    // The Symfony Mailer module prepares the parameters in advance. Alterations
    // should only occur just before the email is sent. To support this, the
    // original parameters are stored here and reinitialized when the queue
    // worker processes the email for sending.
    $email->setOriginalParams($params);
    return $this->initEmail($email, ...$params);
  }

  /**
   * {@inheritdoc}
   */
  public function newEntityEmail(ConfigEntityInterface $entity, string $sub_type, ...$params): EmailInterface {
    // @phpstan-ignore-next-line Following parent implementation.
    $email = QueueableEmail::create(\Drupal::getContainer(), $entity->getEntityTypeId(), $sub_type, $entity); // phpcs:ignore
    // See ::newTypedEmail().
    $email->setOriginalParams($params);
    return $this->initEmail($email, ...$params);
  }

}
