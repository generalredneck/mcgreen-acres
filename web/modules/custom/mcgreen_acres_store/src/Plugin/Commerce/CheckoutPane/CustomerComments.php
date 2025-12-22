<?php

namespace Drupal\mcgreen_acres_store\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Render\Markup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CustomerComments as CheckoutPaneCustomerComments;

/**
 * Provides the customer comments pane.
 */
#[CommerceCheckoutPane(
  id: "customer_comments",
  label: new TranslatableMarkup("Comments"),
  admin_description: new TranslatableMarkup("Allows customers to enter a comment for the order."),
  default_step: "_disabled",
  wrapper_element: "fieldset",
)]
class CustomerComments extends CheckoutPaneCustomerComments {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $form = parent::buildPaneForm($pane_form, $form_state, $complete_form);
    $form['comments']['#prefix'] = Markup::create('<p>' . $this->t('This is a great place to add pickup appointment requests, special requests about the product or other important information.') . '</p>');
    return $form;
  }

}
