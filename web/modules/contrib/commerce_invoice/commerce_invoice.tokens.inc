<?php

/**
 * @file
 * Builds placeholder replacement tokens for invoice data.
 */

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function commerce_invoice_token_info() {
  $entity_type_manager = \Drupal::entityTypeManager();
  $entity_type = $entity_type_manager->getDefinition('commerce_invoice');
  assert($entity_type !== NULL);
  $info = [];

  $info['tokens']['commerce_invoice']['url'] = [
    'name' => t('URL'),
    'description' => t('The URL of the invoice.'),
  ];
  $info['tokens']['commerce_invoice']['admin-url'] = [
    'name' => t('URL'),
    'description' => t('The URL for administrators to view the invoice.'),
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function commerce_invoice_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type === 'commerce_invoice' && !empty($data['commerce_invoice'])) {
    $url_options = ['absolute' => TRUE];
    if (isset($options['langcode'])) {
      $url_options['language'] = \Drupal::languageManager()->getLanguage($options['langcode']);
    }

    $invoice = $data['commerce_invoice'];
    assert($invoice instanceof InvoiceInterface);

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'url':
          $url = $invoice->toUrl('user-view', $url_options)->toString(TRUE);
          assert($url instanceof GeneratedUrl);
          $bubbleable_metadata->addCacheableDependency($url);
          $replacements[$original] = $url->getGeneratedUrl();
          break;

        case 'admin-url':
          $url = $invoice->toUrl('canonical', $url_options)->toString(TRUE);
          assert($url instanceof GeneratedUrl);
          $bubbleable_metadata->addCacheableDependency($url);
          $replacements[$original] = $url->getGeneratedUrl();
          break;
      }
    }
  }

  return $replacements;
}
