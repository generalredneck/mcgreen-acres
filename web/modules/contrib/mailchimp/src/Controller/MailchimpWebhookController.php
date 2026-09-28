<?php

namespace Drupal\mailchimp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\mailchimp\ApiService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mailchimp Webhook controller.
 */
class MailchimpWebhookController extends ControllerBase {

  /**
   * The Mailchimp API service.
   *
   * @var \Drupal\mailchimp\ApiService
   */
  protected $apiService;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->apiService = $container->get('mailchimp.api');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->logger = $container->get('logger.channel.mailchimp');
    return $instance;
  }

  /**
   * Processes an incoming Mailchimp webhook request.
   *
   * @param string $hash
   *   The webhook hash from the route, validated against the hash stored in
   *   configuration.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A plain text response indicating whether the webhook was processed.
   */
  public function endpoint($hash) {
    $return = 0;

    // Return early if the hash in the request does not match.
    $webhook_hash = $this->config('mailchimp.settings')->get('webhook_hash');
    if (!empty($webhook_hash) && !hash_equals($webhook_hash, $hash)) {
      $response = new Response(
        $return,
        Response::HTTP_FORBIDDEN,
        ['content-type' => 'text/plain']
      );
      return $response;
    }

    if (!empty($_POST)) {
      $data = $_POST['data'];
      $type = $_POST['type'];
      switch ($type) {
        case 'unsubscribe':
        case 'profile':
        case 'cleaned':
          $this->apiService->getMemberInfo($data['list_id'], $data['email'], TRUE);
          break;

        case 'upemail':
          mailchimp_cache_clear_member($data['list_id'], $data['old_email']);
          $this->apiService->getMemberInfo($data['list_id'], $data['new_email'], TRUE);
          break;

        case 'campaign':
          mailchimp_cache_clear_list_activity($data['list_id']);
          mailchimp_cache_clear_campaign($data['id']);
          break;
      }

      // Allow other modules to act on a webhook.
      $this->moduleHandler->invokeAll('mailchimp_process_webhook', [
        $type,
        $data,
      ]);

      // Log event.
      $this->logger->info('Webhook type {type} has been processed.', [
        'type' => $type,
      ]);

      $return = 1;
    }

    $response = new Response(
      $return,
      Response::HTTP_OK,
      ['content-type' => 'text/plain']
    );
    return $response;
  }

}
