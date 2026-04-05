<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats;

use Drupal\Core\Entity\EntityInterface;
use Drupal\simplenews\SubscriberInterface;
use Drupal\symfony_mailer\Email;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;

/**
 * Class SimplenewsStatsMail.
 */
class SimplenewsStatsMailSymfony extends SimplenewsStatsMailBase {

  /**
   * Prepare the mail by adding to it tags and image Tracker.
   *
   * @param \Drupal\symfony_mailer\Email $email
   *   The email object.
   */
  public function prepareMail(Email $email): void {
    $subscriber = $email->getParam('simplenews_subscriber');
    $entity = $email->getParam('issue');

    $build = [];
    $this->addImageTracker($subscriber, $entity, $build, $email);

    $build = [];
    $this->addTags($subscriber, $entity, $build, $email);

    $this->logHitSent($subscriber, $entity);
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
   * {@inheritdoc}
   */
  protected function addImageTracker(SubscriberInterface $subscriber, EntityInterface $entity, array &$build = [], Email|null $email = NULL): SimplenewsStatsMailInterface {
    // Do not add image if this user is not registered.
    if (!$subscriber->id()) {
      return $this;
    }

    if (!$email) {
      throw new MissingMandatoryParametersException('The Email parameter is missing.');
    }

    parent::addImageTracker($subscriber, $entity, $build);

    $email->setHtmlBody($email->getHtmlBody() . $this->renderer->renderRoot($build));

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function addTags(SubscriberInterface $subscriber, EntityInterface $entity, &$body = '', Email|null $email = NULL): SimplenewsStatsMailInterface {
    if (!$email) {
      throw new MissingMandatoryParametersException('The Email parameter is missing.');
    }

    $body = $email->getHtmlBody();

    parent::addTags($subscriber, $entity, $body);
    if (is_array($body)) {
      $new_body = '';
      foreach ($body as $b) {
        $new_body .= $b;
      }
      $body = $new_body;
    }

    $email->setHtmlBody($body);

    return $this;
  }

}
