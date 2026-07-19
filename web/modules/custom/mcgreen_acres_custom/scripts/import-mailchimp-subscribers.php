<?php

/**
 * @file
 * One-time import of legacy Mailchimp subscribers into simplenews.
 *
 * Usage: lando drush scr .../import-mailchimp-subscribers.php
 *   -- /path/to/export.csv.
 *
 * Only email address is required per row; every other column is optional
 * and left empty on the subscriber when not present in the CSV. No Drupal
 * user accounts are created — Subscriber::postCreate()/preSave() already
 * link an existing account by matching email automatically.
 */

use Drupal\simplenews\Entity\Subscriber;
use Drupal\taxonomy\Entity\Term;

$newsletter_id = 'mcgreen_acres_newsletter';
$tags_vocabulary = 'subscriber_tags';

$csv_path = $extra[0] ?? NULL;
if (!$csv_path || !is_readable($csv_path)) {
  throw new \Exception('Provide a readable CSV path: drush scr .../import-mailchimp-subscribers.php -- /path/to/export.csv');
}

$handle = fopen($csv_path, 'r');
$header = array_map('trim', fgetcsv($handle));

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$email_validator = \Drupal::service('email.validator');
$tag_term_cache = [];

$created = 0;
$updated = 0;
$skipped = 0;

while (($row = fgetcsv($handle)) !== FALSE) {
  $data = array_combine($header, $row);

  $mail = trim($data['Email Address'] ?? '');
  if (!$mail || !$email_validator->isValid($mail)) {
    $skipped++;
    continue;
  }

  $subscriber = Subscriber::loadByMail($mail, TRUE);
  $is_new = $subscriber->isNew();

  if (!empty($data['First Name'])) {
    $subscriber->set('field_first_name', $data['First Name']);
  }
  if (!empty($data['Last Name'])) {
    $subscriber->set('field_last_name', $data['Last Name']);
  }
  if (!empty($data['Phone Number'])) {
    $subscriber->set('field_phone', $data['Phone Number']);
  }
  if (!empty($data['Address'])) {
    $subscriber->set('field_location', $data['Address']);
  }
  if (!empty($data['OPTIN_IP'])) {
    $subscriber->set('field_optin_ip', $data['OPTIN_IP']);
  }
  if (!empty($data['OPTIN_TIME'])) {
    $timestamp = strtotime($data['OPTIN_TIME']);
    if ($timestamp) {
      $subscriber->set('created', $timestamp);
    }
  }

  // TAGS is a quoted, comma-separated list within the outer CSV field, e.g.
  // """newsletter"",""HerdShareWaitList"",""honey guide""" parses to the raw
  // string "newsletter","HerdShareWaitList","honey guide", which is itself
  // valid CSV.
  if (!empty($data['TAGS'])) {
    $term_ids = [];
    foreach (str_getcsv($data['TAGS']) as $tag_name) {
      $tag_name = trim($tag_name);
      if ($tag_name === '') {
        continue;
      }
      if (!isset($tag_term_cache[$tag_name])) {
        $existing = $term_storage->loadByProperties([
          'vid' => $tags_vocabulary,
          'name' => $tag_name,
        ]);
        $term = reset($existing);
        if (!$term) {
          $term = Term::create([
            'vid' => $tags_vocabulary,
            'name' => $tag_name,
          ]);
          $term->save();
        }
        $tag_term_cache[$tag_name] = $term->id();
      }
      $term_ids[] = $tag_term_cache[$tag_name];
    }
    $subscriber->set('field_tags', $term_ids);
  }

  $subscriber->subscribe($newsletter_id);
  $subscriber->save();

  $is_new ? $created++ : $updated++;
}

fclose($handle);

echo "Import complete: {$created} created, {$updated} updated, {$skipped} skipped (no valid email).\n";
