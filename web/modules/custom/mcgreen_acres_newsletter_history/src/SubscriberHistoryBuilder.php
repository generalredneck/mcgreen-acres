<?php

namespace Drupal\mcgreen_acres_newsletter_history;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simplenews\SubscriberHistoryInterface;
use Drupal\simplenews\SubscriberInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Builds a render array describing a subscriber's Simplenews history.
 *
 * Simplenews records every confirmed subscribe / unsubscribe as a
 * simplenews_subscriber_history entity but never surfaces it. This turns those
 * rows into an admin-readable table: when the change happened, what changed,
 * where it came from (the route the request hit) and who did it.
 */
class SubscriberHistoryBuilder {

  use StringTranslationTrait;

  /**
   * Friendly labels for the route names Simplenews commonly records.
   *
   * Keyed by route machine name. Anything not listed falls back to the raw
   * route name (still shown in parentheses for every entry, greppable).
   */
  protected const ROUTE_LABELS = [
    'entity.node.canonical' => 'Signed up from a page or article',
    '<front>' => 'Signed up from the front page',
    'simplenews.newsletter_subscriptions_user' => 'Changed in their user-account newsletter settings',
    'entity.user.canonical' => 'User account page',
    'entity.user.edit_form' => 'User account edit page',
    'simplenews.subscriptions_manage' => 'Used a subscription-management link from an email',
    'simplenews.subscriptions_confirm' => 'Confirmed via opt-in email link',
    'simplenews.subscriptions_confirm_immediate' => 'Confirmed via opt-in email link',
    'simplenews.subscriptions_add' => 'Added via a link from an email',
    'simplenews.subscriptions_add_immediate' => 'Added via a link from an email',
    'simplenews.subscriptions_remove' => 'Unsubscribed via a link from an email',
    'simplenews.subscriptions_remove_immediate' => 'Unsubscribed via a link from an email',
    'entity.simplenews_subscriber.edit_form' => 'Edited by staff in the admin UI',
    'entity.simplenews_subscriber.add_form' => 'Added by staff in the admin UI',
    'simplenews.subscriber_import' => 'Staff mass-subscribe form',
    'simplenews.subscriber_unsubscribe' => 'Staff mass-unsubscribe form',
    'commerce_checkout.form' => 'Opted in during checkout',
    'entity.webform.canonical' => 'Submitted a webform',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DateFormatterInterface $dateFormatter,
    protected RouteProviderInterface $routeProvider,
  ) {}

  /**
   * Builds the history render array for a subscriber.
   *
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The subscriber to report on.
   *
   * @return array
   *   A render array.
   */
  public function build(SubscriberInterface $subscriber): array {
    $records = $this->loadHistory($subscriber->getMail());

    if (!$records) {
      return [
        'empty' => [
          '#markup' => $this->t('No history has been recorded for %mail. Simplenews only started logging subscription history in a recent release, and it only records changes while a subscriber is confirmed — anything earlier will not appear here.', [
            '%mail' => $subscriber->getMail(),
          ]),
          '#prefix' => '<p>',
          '#suffix' => '</p>',
        ],
      ];
    }

    $labels = $this->newsletterLabels();
    $rows = [];
    $previous = [];
    $first = TRUE;
    foreach ($records as $record) {
      $current = $record->getSubscribedNewsletterIds();
      $rows[] = [
        'data' => [
          'date' => $this->dateFormatter->format($record->getTimestamp(), 'short'),
          'event' => $this->describeEvent($previous, $current, $labels, $first),
          'newsletters' => $current
            ? implode(', ', array_map(fn ($id) => (string) ($labels[$id] ?? $id), $current))
            : $this->t('None'),
          'source' => $this->describeSource($record),
          'actor' => ['data' => $this->describeActor($record)],
        ],
      ];
      $previous = $current;
      $first = FALSE;
    }

    // Newest first.
    $rows = array_reverse($rows);

    $earliest = reset($records);
    $build = [];
    $build['summary'] = [
      '#markup' => $this->t('First recorded activity: %date — %source. %count change(s) on record.', [
        '%date' => $this->dateFormatter->format($earliest->getTimestamp(), 'short'),
        '%source' => $this->describeSource($earliest),
        '%count' => count($records),
      ]),
      '#prefix' => '<p><strong>',
      '#suffix' => '</strong></p>',
    ];
    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        'date' => $this->t('When'),
        'event' => $this->t('Change'),
        'newsletters' => $this->t('Subscribed after change'),
        'source' => $this->t('Source'),
        'actor' => $this->t('By'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No history.'),
      '#attributes' => ['class' => ['mcgreen-acres-newsletter-history']],
    ];

    return $build;
  }

  /**
   * Loads all history records for an email, oldest first.
   *
   * @return \Drupal\simplenews\SubscriberHistoryInterface[]
   *   History entities keyed by id, ordered by timestamp then id ascending.
   */
  protected function loadHistory(string $mail): array {
    $storage = $this->entityTypeManager->getStorage('simplenews_subscriber_history');
    $ids = $storage->getQuery()
      ->condition('mail', $mail)
      ->sort('timestamp')
      ->sort('id')
      ->accessCheck(FALSE)
      ->execute();

    return $ids ? $storage->loadMultiple($ids) : [];
  }

  /**
   * Describes what changed between two snapshots of subscribed newsletter ids.
   */
  protected function describeEvent(array $previous, array $current, array $labels, bool $first) {
    $names = fn (array $ids) => implode(', ', array_map(fn ($id) => (string) ($labels[$id] ?? $id), $ids));
    $added = array_values(array_diff($current, $previous));
    $removed = array_values(array_diff($previous, $current));

    if ($first) {
      return $current
        ? $this->t('Subscribed to @n', ['@n' => $names($current)])
        : $this->t('Subscriber record created');
    }
    if ($added && $removed) {
      return $this->t('Added @a, removed @r', ['@a' => $names($added), '@r' => $names($removed)]);
    }
    if ($added) {
      return $this->t('Subscribed to @a', ['@a' => $names($added)]);
    }
    if ($removed) {
      return $current
        ? $this->t('Unsubscribed from @r', ['@r' => $names($removed)])
        : $this->t('Unsubscribed from all newsletters');
    }
    return $this->t('Re-subscribed or details updated (no newsletter change)');
  }

  /**
   * Turns a history record's stored source into a readable description.
   */
  protected function describeSource(SubscriberHistoryInterface $record) {
    $raw = (string) ($record->get('source')->value ?? '');

    if (!str_starts_with($raw, 'route:')) {
      return $raw !== '' ? $raw : $this->t('Unknown');
    }

    $route_name = substr($raw, strlen('route:'));
    if ($route_name === '' || $route_name === '<none>') {
      return $this->t('Programmatic — import, Drush, cron or migration (no web request)');
    }

    if (isset(static::ROUTE_LABELS[$route_name])) {
      return $this->t('@label (@route)', [
        '@label' => static::ROUTE_LABELS[$route_name],
        '@route' => $route_name,
      ]);
    }

    // Unmapped route: show its configured title if it has one, else just the
    // machine name.
    try {
      $title = $this->routeProvider->getRouteByName($route_name)->getDefault('_title');
    }
    catch (RouteNotFoundException) {
      $title = NULL;
    }
    return $title
      ? $this->t('@title (@route)', ['@title' => $title, '@route' => $route_name])
      : $route_name;
  }

  /**
   * Renders the acting user for a history record.
   */
  protected function describeActor(SubscriberHistoryInterface $record): array {
    $uid = (int) ($record->get('uid')->target_id ?? 0);
    if ($uid === 0) {
      return ['#markup' => $this->t('Anonymous / self-service')];
    }
    $author = $record->getAuthor();
    if ($author && !$author->isAnonymous()) {
      return $author->toLink()->toRenderable();
    }
    return ['#plain_text' => (string) $this->t('User #@uid (deleted)', ['@uid' => $uid])];
  }

  /**
   * Returns newsletter id => label for every newsletter.
   */
  protected function newsletterLabels(): array {
    $labels = [];
    foreach ($this->entityTypeManager->getStorage('simplenews_newsletter')->loadMultiple() as $id => $newsletter) {
      $labels[$id] = (string) $newsletter->label();
    }
    return $labels;
  }

}
