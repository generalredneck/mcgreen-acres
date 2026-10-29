<?php

namespace Drupal\commerce_stripe\Controller;

use Drupal\commerce\Utility\Error;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_stripe\StripeHelper;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides an OAUth return callback.
 */
class StripeConnectController extends ControllerBase {

  /**
   * The client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $clientFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->clientFactory = $container->get('http_client_factory');
    $instance->messenger = $container->get('messenger');
    $instance->logger = $container->get('logger.channel.commerce_stripe');
    return $instance;
  }

  /**
   * Handles returning from Stripe after a successful OAuth authentication.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $commerce_payment_gateway
   *   The payment gateway.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function oauthReturn(PaymentGatewayInterface $commerce_payment_gateway, Request $request): RedirectResponse {
    $plugin = $commerce_payment_gateway->getPlugin();
    // @todo move this to an access handler?
    if (!$request->query->has('code') ||
      !StripeHelper::isStripeGateway($plugin)) {
      throw new AccessDeniedHttpException();
    }

    // Validate the state token to protect against CSRF attacks that could
    // otherwise bind this gateway to an attacker-controlled Stripe account.
    // The token stays in the session (rather than being consumed here) so
    // that a transient network failure or page reload can be retried
    // against the same token instead of hitting a permanent access-denied
    // error. Once the connection actually succeeds below, it's flagged as
    // completed so a later reload of the same URL can be recognized as a
    // harmless repeat rather than re-attempted or rejected.
    $session = $request->getSession();
    $session_key = 'commerce_stripe_connect_state.' . $commerce_payment_gateway->id();
    $stored_state = $session->get($session_key);
    $expected_state = is_array($stored_state) ? ($stored_state['token'] ?? NULL) : NULL;
    $state = $request->query->get('state');
    if (empty($expected_state) || empty($state) || !hash_equals($expected_state, $state)) {
      throw new AccessDeniedHttpException();
    }
    if (!empty($stored_state['completed'])) {
      // This exact connection already succeeded (e.g. the browser lost the
      // response and reloaded); the success message is already queued in
      // the session, so just send them back without repeating the exchange.
      return new RedirectResponse($commerce_payment_gateway->toUrl('collection')->toString());
    }
    $client = $this->clientFactory->fromOptions();
    try {
      $response = $client->post(StripeHelper::BASE_CONNECT_URL . '/oauth/token', [
        'form_params' => [
          'code' => $request->query->get('code'),
        ],
      ]);
      $response = Json::decode($response->getBody()->getContents());
      if (isset($response['access_token'], $response['stripe_publishable_key'])) {
        $access_token = $response['access_token'];
        $masked_access_token = strlen($access_token) > 4 ? str_repeat('•', strlen($access_token) - 4) . substr($access_token, -4) : $access_token;
        $message = $this->t('Stripe credentials generated and saved to the payment gateway configuration.<br/><strong>Publishable key:</strong> @publishable_key<br/><strong>Access token:</strong> @access_token<br/><strong>Stripe User ID:</strong> @stripe_user_id', [
          '@publishable_key' => $response['stripe_publishable_key'],
          '@access_token' => $masked_access_token,
          '@stripe_user_id' => $response['stripe_user_id'] ?? '',
        ]);
        $this->messenger->addWarning($message);
        $configuration = $commerce_payment_gateway->getPluginConfiguration();
        $configuration['publishable_key'] = $response['stripe_publishable_key'];
        $configuration['access_token'] = $response['access_token'];
        if (isset($response['stripe_user_id'])) {
          $configuration['stripe_user_id'] = $response['stripe_user_id'];
        }
        unset($configuration['secret_key']);
        $commerce_payment_gateway->setPluginConfiguration($configuration);
        $commerce_payment_gateway->save();
        $session->set($session_key, ['token' => $expected_state, 'completed' => TRUE]);

        return new RedirectResponse($commerce_payment_gateway->toUrl('collection')->toString());
      }
    }
    catch (RequestException $exception) {
      Error::logException($this->logger, $exception);
    }

    $this->messenger->addError($this->t('There was an error connecting to Stripe.'));
    return new RedirectResponse($commerce_payment_gateway->toUrl('collection')->toString());
  }

}
