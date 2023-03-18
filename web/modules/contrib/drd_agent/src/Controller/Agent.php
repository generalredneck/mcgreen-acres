<?php

namespace Drupal\drd_agent\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\State\StateInterface;
use Drupal\drd_agent\Agent\Action\Base as ActionBase;
use Drupal\drd_agent\Crypt\Base as CryptBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Default.
 *
 * @package Drupal\drd_agent\Controller
 */
class Agent extends ControllerBase {

  /**
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;
  /**
   * Get an array of http response headers.
   *
   * @return array
   *   The array with headers.
   */
  public static function responseHeader(): array {
    return [
      'Content-Type' => 'text/plain; charset=utf-8',
      'X-DRD-Agent' => $_SERVER['HTTP_X_DRD_VERSION'],
    ];
  }

  /**
   * Agent constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param \Drupal\Core\State\StateInterface $state
   */
  public function __construct(ContainerInterface $container, StateInterface $state) {
    $this->container = $container;
    $this->state = $state;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container,
      $container->get('state')
    );
  }

  /**
   * Route callback to execute an action and return their result.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response to DRD.
   * @throws \Exception
   */
  public function get(): Response {
    return $this->deliver(ActionBase::create($this->container)->run((bool) $this->state->get('drd_agent.debug_mode', FALSE)));
  }

  /**
   * Route callback to retrieve a list of available crypt methods.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response to DRD.
   * @throws \Exception
   */
  public function getCryptMethods(): Response {
    return $this->deliver(base64_encode(json_encode(CryptBase::getMethods($this->container))));
  }

  /**
   * Route callback to authorize a DRD instance by a secret.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response to DRD.
   * @throws \Exception
   */
  public function authorizeBySecret(): Response {
    return $this->deliver(ActionBase::create($this->container)->authorizeBySecret((bool) $this->state->get('drd_agent.debug_mode', FALSE)));
  }

  /**
   * Callback to deliver the result of the action in json format.
   *
   * @param string|Response $data
   *   The result which should be delivered back to DRD.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response to DRD.
   */
  private function deliver($data): Response {
    return ($data instanceof Response) ? $data : new JsonResponse($data, 200, self::responseHeader());
  }

}
