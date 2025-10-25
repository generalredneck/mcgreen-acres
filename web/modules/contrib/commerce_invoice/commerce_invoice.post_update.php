<?php

/**
 * @file
 * Post update functions for Invoice.
 */

/**
 * Revert the user invoices view to add the customer contextual filter.
 */
function commerce_invoice_post_update_1() {
  /** @var \Drupal\commerce\Config\ConfigUpdaterInterface $config_updater */
  $config_updater = \Drupal::service('commerce.config_updater');

  $views = [
    'views.view.commerce_user_invoices',
  ];
  $result = $config_updater->revert($views, FALSE);

  $message = '';
  $success_results = $result->getSucceeded();
  $failure_results = $result->getFailed();
  if ($success_results) {
    $message = t('Succeeded:') . '<br>';
    foreach ($success_results as $success_message) {
      $message .= $success_message . '<br>';
    }
    $message .= '<br>';
  }
  if ($failure_results) {
    $message .= t('Failed:') . '<br>';
    foreach ($failure_results as $failure_message) {
      $message .= $failure_message . '<br>';
    }
  }

  return $message;
}

/**
 * Revert the default invoice type and number pattern to change their labels.
 */
function commerce_invoice_post_update_2() {
  /** @var \Drupal\commerce\Config\ConfigUpdaterInterface $config_updater */
  $config_updater = \Drupal::service('commerce.config_updater');

  $config_names = [
    'commerce_invoice.commerce_invoice_type.default',
    'commerce_number_pattern.commerce_number_pattern.invoice_default',
  ];
  $result = $config_updater->revert($config_names);
  $message = implode('<br>', $result->getFailed());

  return $message;
}

/**
 * Revert the order invoices view to add the new credit memo page.
 */
function commerce_invoice_post_update_3() {
  /** @var \Drupal\commerce\Config\ConfigUpdaterInterface $config_updater */
  $config_updater = \Drupal::service('commerce.config_updater');

  $config_names = [
    'views.view.order_invoices',
  ];
  $result = $config_updater->revert($config_names, FALSE);
  $message = implode('<br>', $result->getFailed());

  $config_names = [
    'commerce_invoice.commerce_invoice_type.credit_memo',
    'commerce_number_pattern.commerce_number_pattern.invoice_credit_memo',
  ];
  $result = $config_updater->import($config_names);
  if ($result->getFailed()) {
    $message .= '<br>' . implode('<br>', $result->getFailed());
  }

  return $message;
}

/**
 * Revert the order invoices view to improve a few labels.
 */
function commerce_invoice_post_update_4() {
  /** @var \Drupal\commerce\Config\ConfigUpdaterInterface $config_updater */
  $config_updater = \Drupal::service('commerce.config_updater');

  $config_names = [
    'views.view.order_invoices',
  ];
  $result = $config_updater->revert($config_names, FALSE);
  $message = implode('<br>', $result->getFailed());

  return $message;
}

/**
 * Alter commerce_user_invoices view and create the "user" view mode.
 */
function commerce_invoice_post_update_5() {
  /** @var \Drupal\commerce\Config\ConfigUpdaterInterface $config_updater */
  $config_updater = \Drupal::service('commerce.config_updater');
  $result = $config_updater->revert([
    'views.view.commerce_user_invoices',
    'core.entity_view_mode.commerce_invoice.user',
  ], FALSE);
  $message = implode('<br>', $result->getFailed());

  return $message;
}

/**
 * Update the customer invoices view to fix the default url rewrite pattern.
 */
function commerce_invoice_post_update_6() {
  $config_factory = \Drupal::configFactory();
  $view = $config_factory->getEditable('views.view.commerce_user_invoices');
  if ($view->get('display.default.display_options.fields.invoice_number.alter.path') === 'user/{{ raw_arguments.uid }}/invoices/{{ invoice_id }}') {
    return "The view is already up-to-date.";
  }

  if ($view->get('display.default.display_options.fields.invoice_number.alter.path') === 'user/{{ arguments.uid }}/invoices/{{ invoice_id }}') {
    $view->set('display.default.display_options.fields.invoice_number.alter.path', 'user/{{ raw_arguments.uid }}/invoices/{{ invoice_id }}');
    $view->save(TRUE);
    return "The views.view.commerce_user_invoices view was updated";
  }

  return "The views.view.commerce_user_invoices couldn't be updated as the default path for the title field has been overridden with custom value. Test if rewrite pattern is correct.";
}
