<?php

namespace Drupal\commerce_invoice;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for the Invoice entity.
 */
class InvoiceRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);
    $entity_type_id = $entity_type->id();

    if ($download_route = $this->getDownloadRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.download", $download_route);
    }
    if ($user_view_route = $this->getUserViewRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.user_view", $user_view_route);
    }
    if ($invoice_payment_route = $this->getInvoicePaymentFormRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.payment_form", $invoice_payment_route);
    }
    if ($resend_confirmation_form_route = $this->getResendConfirmationFormRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.resend_confirmation_form", $resend_confirmation_form_route);
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCanonicalRoute($entity_type);
    // Replace the 'full' view mode with the 'admin' view mode.
    $route->setDefault('_entity_view', 'commerce_invoice.admin');
    $route->addRequirements([
      '_permission' => 'access commerce administration pages',
    ]);

    // The canonical route is the admin view of the order.
    $route->setOption('_admin_route', TRUE);
    return $route;
  }

  /**
   * Gets the download route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getDownloadRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('download')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('download'));
      $route
        ->addDefaults([
          '_controller' => '\Drupal\commerce_invoice\Controller\InvoiceController::download',
        ])
        ->setRequirement('_custom_access', '\Drupal\commerce_invoice\Access\InvoiceUserViewAccessCheck::checkAccess')
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      // Entity types with serial IDs can specify this in their route
      // requirements, improving the matching process.
      if ($this->getEntityTypeIdKeyType($entity_type) === 'integer') {
        $route->setRequirement($entity_type_id, '\d+');
      }
      return $route;
    }
  }

  /**
   * Gets the invoice payment-form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getInvoicePaymentFormRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('payment-form')) {
      $route = new Route($entity_type->getLinkTemplate('payment-form'));
      $entity_type_id = $entity_type->id();
      $route
        ->setDefaults([
          '_form' => '\Drupal\commerce_invoice\Form\InvoicePaymentForm',
        ])
        ->setRequirement('_entity_access', "{$entity_type_id}.update")
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      // Entity types with serial IDs can specify this in their route
      // requirements, improving the matching process.
      if ($this->getEntityTypeIdKeyType($entity_type) === 'integer') {
        $route->setRequirement($entity_type_id, '\d+');
      }

      return $route;
    }
  }

  /**
   * Gets the invoice user view route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getUserViewRoute(EntityTypeInterface $entity_type): ?Route {
    if ($entity_type->hasLinkTemplate('user-view')) {
      $route = new Route($entity_type->getLinkTemplate('user-view'));
      $entity_type_id = $entity_type->id();
      $route
        ->setDefaults([
          '_entity_view' => "{$entity_type_id}.user",
          '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::title',
        ])
        ->setRequirement('_custom_access', '\Drupal\commerce_invoice\Access\InvoiceUserViewAccessCheck::checkAccess')
        ->setRequirement('user', '\d+')
        ->setRequirement('commerce_invoice', '\d+')
        ->setOption('parameters', [
          'user' => [
            'type' => 'entity:user',
          ],
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      return $route;
    }

    return NULL;
  }

  /**
   * Gets the resend-confirmation-form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getResendConfirmationFormRoute(EntityTypeInterface $entity_type): ?Route {
    if ($entity_type->hasLinkTemplate('resend-confirmation-form')) {
      $route = new Route($entity_type->getLinkTemplate('resend-confirmation-form'));
      $entity_type_id = $entity_type->id();
      $route
        ->addDefaults([
          '_entity_form' => $entity_type_id . '.resend-confirmation',
          '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::title',
        ])
        ->setRequirement('_entity_access', $entity_type_id . '.resend_confirmation')
        ->setRequirement($entity_type_id, '\d+')
        ->setOption('parameters', [
          'commerce_invoice' => [
            'type' => 'entity:' . $entity_type_id,
          ],
        ])
        ->setOption('_admin_route', TRUE);

      return $route;
    }

    return NULL;
  }

}
