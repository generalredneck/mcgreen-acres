<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\simplenews\SubscriberInterface;

/**
 * Base class for Simplenews stats mail.
 */
abstract class SimplenewsStatsMailBase implements SimplenewsStatsMailInterface {

  public function __construct(
    protected SimplenewsStatsEngine $simplenewsStatsEngine,
    protected SimplenewsStatsAllowedLinks $simplenewsStatsAllowedLinks,
    protected RendererInterface $renderer,
  ) {}

  /**
   * AddTags on every link in the mail.
   *
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The simplenews subscriber.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity use as simplenews.
   * @param array $body
   *   The body of the mail.
   *
   * @return static
   */
  protected function addTags(SubscriberInterface $subscriber, EntityInterface $entity, &$body = ''): SimplenewsStatsMailInterface {
    if (!is_array($body)) {
      $body = [$body];
    }

    foreach ($body as $delta => $content) {
      $raw_content = $content instanceof MarkupInterface ? (string) $content : $content;

      // Add tags on links.
      $body[$delta] = preg_replace_callback("`<a.*href=\"([a-zA-Z\d@:%_+*~#?&=.,/;-]*[a-zA-Z\d@:%_+*~#&?=/;-])\"`Ui",
        function ($match) use ($entity, $subscriber) {
          return $this->replaceLinksUrl($subscriber, $entity, $match);
        },
        $raw_content);

      $body[$delta] = Markup::create($body[$delta]);
    }

    return $this;
  }

  /**
   * Adds image tracker to the body.
   *
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The simplenews subscriber.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity use as simplenews.
   * @param array $build
   *   Array renderable of the image tracker to add to the body of the mail.
   *
   * @return static
   */
  protected function addImageTracker(SubscriberInterface $subscriber, EntityInterface $entity, array &$build = []): SimplenewsStatsMailInterface {
    $tag = $this->getTag($subscriber, $entity);
    $url = Url::fromRoute('simplenews_stats.hit_view')
      ->setOption('query', ['sstc' => $tag])
      ->setAbsolute();

    $build = [
      '#theme' => 'image',
      '#attributes' => [
        'src' => $url->toString(),
      ],
    ];

    return $this;
  }

  /**
   * Callback of AddTags.
   *
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The simplenews subscriber object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The issue entity.
   * @param array $url
   *   The url to replace.
   *
   * @return string
   *   The new string.
   */
  protected function replaceLinksUrl(SubscriberInterface $subscriber, EntityInterface $entity, array $url): string {
    $external = FALSE;
    $url_cleaned = html_entity_decode(strtolower($url[1]));

    // Escape if email is not registered.
    if (!$subscriber->id()) {
      return $url[0];
    }

    $url_obj = NULL;

    // Find Url Type.
    if (preg_match('/^https?:\/\//', $url_cleaned)) {
      $external = TRUE;

      if (!$this->simplenewsStatsAllowedLinks->isLinkExist($entity, $url[1])) {
        $this->simplenewsStatsAllowedLinks->add($entity, $url[1]);
      }
      $url_obj = $this->generateExternalLink($subscriber, $entity, $url[1]);
    }
    elseif (str_starts_with($url_cleaned, 'mailto:')) {
      // Do nothing on mailto link.
      return $url[0];
    }
    elseif ($url_cleaned[0] === '/') {
      $url_obj = Url::fromUri('internal:' . $url_cleaned);
    }
    else {
      try {
        $url_obj = Url::fromUri('internal:/' . $url_cleaned);
      }
      catch (\Exception $exception) {
        // Do nothing, no pattern founded.
        return $url[0];
      }
    }

    // Do nothing, no pattern founded.
    if (!$url_obj instanceof Url) {
      return $url[0];
    }

    $tag = $this->getTag($subscriber, $entity);
    if (!$external) {
      $queries = $url_obj->getOption('query');
      if (!$queries) {
        $queries = [];
      }
      $queries = array_merge($queries, ['sstc' => $tag]);
      $url_obj->setOption('query', $queries);
    }
    $url_obj->setAbsolute();

    return str_replace($url[1], $url_obj->toString(), $url[0]);
  }

  /**
   * Return a link for external link reference.
   *
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The simplenews subscriber.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity use as simplenews.
   * @param string $link
   *   The link to generate.
   *
   * @return \Drupal\Core\Url
   *   The link.
   */
  protected function generateExternalLink(SubscriberInterface $subscriber, EntityInterface $entity, string $link): Url {
    $params = ['tag' => $this->getTag($subscriber, $entity), 'link' => $link];

    return Url::fromRoute('simplenews_stats.hit_click', $params);
  }

  /**
   * Log sent Hit.
   *
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The simplenews subscriber.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity use as simplenews.
   */
  protected function logHitSent(SubscriberInterface $subscriber, EntityInterface $entity): SimplenewsStatsMailInterface {
    $this->simplenewsStatsEngine->logHitSent($subscriber, $entity);
    return $this;
  }

}
