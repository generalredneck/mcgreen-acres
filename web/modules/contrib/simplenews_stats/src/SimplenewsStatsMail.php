<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats;

use Drupal\Core\Render\Markup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\simplenews\Mail\MailEntity;
use Drupal\simplenews\SubscriberInterface;

/**
 * Simplenews stats mail class.
 */
class SimplenewsStatsMail extends SimplenewsStatsMailBase {

  /**
   * The body of the mail.
   *
   * @var array|null
   */
  protected array|null $message = NULL;

  /**
   * The simplenews mail object.
   *
   * @var \Drupal\simplenews\Mail\MailEntity|null
   */
  protected MailEntity|null $simpleNewsMail = NULL;

  /**
   * Prepare the mail by adding to it tags and image Tracker.
   *
   * @param array $message
   *   The mail message array.
   */
  public function prepareMail(array &$message) {
    $this->message = &$message;

    // Store simplenews mail object.
    if (!empty($message['params']['simplenews_mail']) && $message['params']['simplenews_mail'] instanceof MailEntity) {
      $simpleNewsSource = $message['params']['simplenews_mail'];

      $subscriber = $simpleNewsSource->getSubscriber();
      $entity = $simpleNewsSource->getIssue();

      $this->addImageTracker($subscriber, $entity)
        ->addTags($subscriber, $entity, $message['body'])
        ->logHitSent($subscriber, $entity);
    }
  }

  /**
   * Get the body.
   *
   * @return \Drupal\Core\Render\Markup
   *   The body markup.
   */
  protected function getBody() {
    return reset($this->message['body']);
  }

  /**
   * Return the context (Simplenews source object).
   *
   * @return \Drupal\simplenews\Mail\MailEntity|null
   *   The simplenews mail object used as context.
   */
  protected function getContext(): ?MailEntity {
    return $this->simpleNewsMail;
  }

  /**
   * Return the context (Simplenews source object).
   *
   * @return \Drupal\simplenews\SubscriberInterface
   *   The simplenews subscriber.
   */
  protected function getSubscriber(): SubscriberInterface {
    return $this->getContext()->getSubscriber();
  }

  /**
   * Get a tag.
   *
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The simplenews subscriber.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity use as simplenews.
   *
   * @return string
   *   The tag.
   */
  protected function getTag(SubscriberInterface $subscriber, EntityInterface $entity): string {
    return 'u' . $subscriber->id() . 'nl' . $entity->id();
  }

  /**
   * Return the entity from simplenews object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity referenced in the simplenews mail object.
   */
  protected function getEntity(): ?EntityInterface {
    return $this->getContext()?->getNewsletter();
  }

  /**
   * Set the Body.
   *
   * @param string $string
   *   The body string.
   */
  protected function setBody($string) {
    $this->message['body'] = [Markup::create($string)];
  }

  /**
   * {@inheritdoc}
   */
  protected function addImageTracker(SubscriberInterface $subscriber, EntityInterface $entity, array &$build = []): SimplenewsStatsMailInterface {
    // Do not add image if this user is not registered.
    if (!$subscriber->id()) {
      return $this;
    }

    parent::addImageTracker($subscriber, $entity, $build);

    $this->setBody($this->getBody() . $this->renderer->renderRoot($build));

    return $this;
  }

}
