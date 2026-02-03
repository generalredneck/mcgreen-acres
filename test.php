<?php
\Drupal::service('entity_type.manager')->getStorage('commerce_subscription')->resetCache();
$subscription = \Drupal::service('entity_type.manager')->getStorage('commerce_subscription')->load(19);
$service = \Drupal::service('herd_share.agreement_service');
//$pdf_content = $service->generateHtml($subscription);
$pdf_content = $service->generatePdf($subscription);

// Save to file
//file_put_contents('public://agreement.html', $pdf_content);
file_put_contents('public://agreement.pdf', $pdf_content);

exit;
