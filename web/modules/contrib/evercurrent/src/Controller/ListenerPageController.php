<?php

namespace Drupal\evercurrent\Controller;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Access\AccessResult;

/**
 * Creates a callback for listening to the server.
 */
class ListenerPageController {

  /**
   * Check for page access. Only if listening is set to true.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current User account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Access result fo rthis controller.
   */
  public function access(AccountInterface $account) {
    $config = \Drupal::config('evercurrent.admin_config');
    $listen = $config->get('listen');
    return ($listen) ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * Renders the content for a dashboard that is listening.
   *
   * @return Symfony\Component\HttpFoundation\JsonResponse
   *   A json response representing the content.
   */
  public function content() {
    $received = json_decode($_POST['data'], TRUE);
    $updateHelper = \Drupal::service('evercurrent.update_helper');
    // No key equals error response.
    if (!isset($received['key'])) {
      $severity = RMH_STATUS_ERROR;
      $message = 'Received an invalid request in listening mode.';
      $updateHelper->writeStatus($severity, $message, $output = FALSE);
      return $this->jsonResponse('Malformed data.', 'error');
    }
    $key = $received['key'];
    $result = $updateHelper->testUpdate($key);
    if ($result == TRUE) {
      $this->jsonResponse('Success.', 'status');
    }
    else {
      $this->jsonResponse('Unknown error.', 'error');
    }
    return $this->jsonResponse('Can you see this message?', 'error');
  }

  /**
   * Wrapper function for the json response.
   *
   * @param mixed $message
   *   The message we are sending.
   * @param string $type
   *   One of "error" or "status".
   *
   * @return Symfony\Component\HttpFoundation\JsonResponse
   *   Message and Type wrapped in a json structure.
   */
  public function jsonResponse($message, $type) {
    return new JsonResponse(
      [
        'data' => [
          'message' => $message,
          'type' => $type,
        ],
      ]
    );
  }

}
