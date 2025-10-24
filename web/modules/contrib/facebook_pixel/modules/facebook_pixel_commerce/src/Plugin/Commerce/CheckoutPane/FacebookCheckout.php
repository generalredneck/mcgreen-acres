<?php

namespace Drupal\facebook_pixel_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facebook_pixel\FacebookEventInterface;
use Drupal\facebook_pixel_commerce\FacebookCommerceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the completion message pane.
 *
 * Hijack the pane form subsystem so that we can
 * call our addEvent for initialize checkout on the
 * first stage of checkout.
 *
 * @CommerceCheckoutPane(
 *   id = "facebook_checkout",
 *   label = @Translation("Facebook Pixel Commerce: Trigger 'InitiateCheckout' Event (order_information)"),
 *   default_step = "order_information",
 * )
 */
class FacebookCheckout extends CheckoutPaneBase {

  /**
   * The facebook pixel event.
   *
   * @var \Drupal\facebook_pixel\FacebookEventInterface
   */
  protected $facebookEvent;

  /**
   * The facebook pixel comment.
   *
   * @var \Drupal\facebook_pixel_commerce\FacebookCommerceInterface
   */
  protected $facebookComment;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new CheckoutPaneBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow
   *   The parent checkout flow.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\facebook_pixel\FacebookEventInterface $facebook_event
   *   The facebook pixel event.
   * @param \Drupal\facebook_pixel_commerce\FacebookCommerceInterface $facebook_comment
   *   The facebook pixel commerce.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager, FacebookEventInterface $facebook_event, FacebookCommerceInterface $facebook_comment, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);

    $this->facebookEvent = $facebook_event;
    $this->facebookComment = $facebook_comment;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('facebook_pixel.facebook_event'),
      $container->get('facebook_pixel_commerce.facebook_commerce'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Only fire the FB event on page load.
    // See https://www.drupal.org/project/facebook_pixel/issues/3246045#comment-14405106
    // for the reasons. Otherwise it's fired multiple times on AJAX requests.
    if (!$this->requestStack->getCurrentRequest()->isXmlHttpRequest()) {
      $data = $this->facebookComment->getOrderData($this->order);
      if (!empty($data)) {
        $this->facebookEvent->addEvent('InitiateCheckout', $data);
      }
    }
    return [];
  }

}
