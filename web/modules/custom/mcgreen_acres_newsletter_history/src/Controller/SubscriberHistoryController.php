<?php

namespace Drupal\mcgreen_acres_newsletter_history\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\mcgreen_acres_newsletter_history\SubscriberHistoryBuilder;
use Drupal\simplenews\SubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the History tab for a Simplenews subscriber.
 */
class SubscriberHistoryController extends ControllerBase {

  public function __construct(protected SubscriberHistoryBuilder $historyBuilder) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('mcgreen_acres_newsletter_history.builder'));
  }

  /**
   * Title callback: "History for <mail>".
   */
  public function title(SubscriberInterface $simplenews_subscriber): string {
    return (string) $this->t('History for @mail', ['@mail' => $simplenews_subscriber->getMail()]);
  }

  /**
   * Page callback: the history table.
   */
  public function view(SubscriberInterface $simplenews_subscriber): array {
    $build = $this->historyBuilder->build($simplenews_subscriber);
    $build['#cache']['contexts'] = ['user.permissions'];
    $build['#cache']['tags'] = Cache::mergeTags(
      $simplenews_subscriber->getCacheTags(),
      ['simplenews_subscriber_history_list'],
    );
    return $build;
  }

}
