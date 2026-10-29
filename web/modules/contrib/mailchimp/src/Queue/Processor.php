<?php

namespace Drupal\mailchimp\Queue;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\mailchimp\ApiService;
use Mailchimp\MailchimpAPIException;
use Psr\Log\LoggerInterface;

/**
 * Facilitates processing MailChimp queue items.
 */
class Processor {

  /**
   * The Mailchimp API service.
   *
   * @var \Drupal\mailchimp\ApiService
   */
  protected $apiService;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $stateService;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new Processor object.
   *
   * @param \Drupal\mailchimp\ApiService $api_service
   *   The API service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logging interface.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(ApiService $api_service, ConfigFactoryInterface $config_factory, QueueFactory $queue_factory, StateInterface $state, LoggerInterface $logger, TimeInterface $time) {
    $this->apiService = $api_service;
    $this->configFactory = $config_factory;
    $this->queueFactory = $queue_factory;
    $this->stateService = $state;
    $this->logger = $logger;
    $this->time = $time;
  }

  /**
   * Processes queue items according to the given or configured limit.
   *
   * @param int|null $batch_limit
   *   The number of items to processed. If NULL given, the limit set in
   *   configuration will be used.
   *
   * @return int
   *   The number of queue items processed.
   */
  public function process(?int $batch_limit = NULL) {
    if (!$this->ping()) {
      $this->logger->warning('Mailchimp API ping failed. Queue processing skipped to prevent data loss.');

      return 0;
    }

    $queue = $this->queueFactory->get(MAILCHIMP_QUEUE_CRON);
    $queue->createQueue();
    $queue_count = $queue->numberOfItems();
    $failed_queue = $this->queueFactory->get(MAILCHIMP_QUEUE_CRON_FAILED);
    $failed_queue->createQueue();

    if ($queue_count === 0) {
      return 0;
    }

    if (is_null($batch_limit)) {
      $batch_limit = $this->configFactory->get('mailchimp.settings')->get('batch_limit');
    }
    $batch_size = ($queue_count < $batch_limit) ? $queue_count : $batch_limit;

    $processed_count = 0;
    while ($processed_count < $batch_size) {
      if ($item = $queue->claimItem()) {
        if (call_user_func_array([$this->apiService, $item->data['function']], $item->data['args'])) {
          $queue->deleteItem($item);
        }
        // Retry once. We don't move failed item to the back of the queue as the
        // order of the items may be important (for items regarding the same
        // email address).
        elseif (call_user_func_array([$this->apiService, $item->data['function']], $item->data['args'])) {
          $queue->deleteItem($item);
        }
        // Move the item to the "failed" queue for manual inspection.
        elseif ($failed_queue->createItem($item->data)) {
          $queue->deleteItem($item);

          $this->logger
            ->error('An update to a subscription failed. Check the mailchimp_failed queue in the queue database table. Alternatively, install the queue_ui module to view through the admin interface.');
        }
      }

      $processed_count++;
    }

    return $processed_count;
  }

  /**
   * Checks Mailchimp API reachability without processing the queue.
   *
   * Used by hook_cron() to keep the connectivity check (and its State/
   * watchdog record) running on every cron invocation even when automatic
   * queue processing is disabled in configuration.
   *
   * @return bool
   *   TRUE if the Mailchimp API responded successfully to a ping.
   */
  public function pingApi(): bool {
    return $this->ping();
  }

  /**
   * Checks Mailchimp reachability via /ping. Result cached in State for 60 s.
   *
   * @return bool
   *   TRUE if the Mailchimp API responded successfully to a ping.
   */
  protected function ping(): bool {
    $now = $this->time->getRequestTime();

    if ($now - (int) $this->stateService->get('mailchimp.ping_time', 0) < 60) {
      return (bool) $this->stateService->get('mailchimp.ping_ok', TRUE);
    }

    $ok = FALSE;
    // Avoid displaying message in case ping fails repeatedly.
    $mc = $this->apiService->getApiObject(notify: FALSE);
    if ($mc) {
      try {
        $response = $mc->ping();
        $ok = property_exists($response, 'health_status');
      }
      catch (MailchimpAPIException $e) {
        $this->logger->error('Mailchimp API ping failed: {message}', ['message' => $e->getMessage()]);
      }
    }

    $this->stateService->set('mailchimp.ping_time', $now);
    $this->stateService->set('mailchimp.ping_ok', $ok);
    return $ok;
  }

}
