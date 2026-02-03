<?php

namespace Drupal\herd_share;

use Drupal\commerce_recurring\Entity\SubscriptionInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\commerce_price\Price;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Service for generating herd share agreement data and PDF.
 */
class HerdShareService {

  /**
   * Extracts data from a subscription for the agreement template.
   *
   * @param \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription
   *   The subscription entity.
   *
   * @return array
   *   An array of variables for the template.
   */
  public function getAgreementData(SubscriptionInterface $subscription) {
    $user = $subscription->getCustomer();
    $quantity = $subscription->getQuantity();
    $unit_price = $subscription->getUnitPrice();

    // Get initial order for billing information.
    $initial_order = $subscription->getInitialOrder();
    $billing_profile = $initial_order ? $initial_order->getBillingProfile() : NULL;

    $owner_name = '';
    $owner_address = '';
    $owner_email = $user->getEmail();

    if ($billing_profile && $billing_profile->hasField('address') && !$billing_profile->get('address')->isEmpty()) {
      $address = $billing_profile->get('address')->first()->getValue();
      $owner_name = $address['given_name'] . ' ' . $address['family_name'];
      $owner_address_line1 = $address['address_line1'];
      if (!empty($address['address_line2'])) {
        $owner_address_line1 .= ', ' . $address['address_line2'];
      }
      $owner_address_line2 = $address['locality'] . ', ' . $address['administrative_area'] . ' ' . $address['postal_code'];
    }
    else {
      // Fallback to user display name.
      $owner_name = $user->getDisplayName();
    }

    // Format price.
    $price_per_share = $unit_price ? $unit_price->getNumber() : '45.00';
    $currency_code = $unit_price ? $unit_price->getCurrencyCode() : 'USD';
    $price_per_share_text = $this->formatPriceText($price_per_share, $currency_code);

    // Purchase price per share, assuming same as unit price.
    $purchase_price_per_share = '15.00';
    $purchase_price_per_share_text = $this->formatPriceText($purchase_price_per_share, $currency_code);

    // Current date.
    $date = new \DateTime();
    $agreement_date = $date->format('j');
    $agreement_month = $date->format('F');
    $agreement_year = $date->format('Y');

    return [
      'agreement_date' => $agreement_date,
      'agreement_month' => $agreement_month,
      'agreement_year' => $agreement_year,
      'owner_name' => $owner_name,
      'shares' => $quantity,
      'price_per_share' => '$' . number_format($price_per_share, 2),
      'price_per_share_text' => $price_per_share_text,
      'herd_share_fee_text' => $purchase_price_per_share_text,
      'owner_address_line1' => $owner_address_line1,
      'owner_address_line2' => $owner_address_line2,
      'owner_email' => $owner_email,
    ];
  }

  /**
   * Formats the price into text like "Forty-Five Dollars ($45.00)".
   *
   * @param string $amount
   *   The amount.
   * @param string $currency
   *   The currency code.
   *
   * @return string
   *   The formatted text.
   */
  private function formatPriceText($amount, $currency) {
    $words = $this->numberToWords($amount);
    return $words . ' Dollars ($' . number_format($amount, 2) . ')';
  }

  /**
   * Generates a PDF from the agreement template for a subscription.
   *
   * @param \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription
   *   The subscription entity.
   *
   * @return string
   *   The PDF content as a string.
   */
  public function generatePdf(SubscriptionInterface $subscription) {
    $html = $this->generateHtml($subscription);
    // Create PDF.
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false); // Disable remote resources for security.

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    return $dompdf->output();
  }

  /**
   * Undocumented function
   *
   * @param \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription
   *
   * @return string
   */
  public function generateHtml(SubscriptionInterface $subscription) {
    $data = $this->getAgreementData($subscription);

    // Render the template.
    $render_array = [
      '#theme' => 'herd-share-agreement',
    ];
    foreach($data as $key => $value) {
      $render_array['#' . $key] = $value;
    }
    $html = \Drupal::service('renderer')->renderRoot($render_array);

    return $html;
  }

  /**
   * Converts a number to words.
   *
   * @param float $number
   *   The number.
   *
   * @return string
   *   The number in words.
   */
  private function numberToWords($number) {
    $number = round($number);
    $words = [
      0 => 'Zero',
      1 => 'One',
      2 => 'Two',
      3 => 'Three',
      4 => 'Four',
      5 => 'Five',
      6 => 'Six',
      7 => 'Seven',
      8 => 'Eight',
      9 => 'Nine',
      10 => 'Ten',
      11 => 'Eleven',
      12 => 'Twelve',
      13 => 'Thirteen',
      14 => 'Fourteen',
      15 => 'Fifteen',
      16 => 'Sixteen',
      17 => 'Seventeen',
      18 => 'Eighteen',
      19 => 'Nineteen',
      20 => 'Twenty',
      21 => 'Twenty-One',
      22 => 'Twenty-Two',
      23 => 'Twenty-Three',
      24 => 'Twenty-Four',
      25 => 'Twenty-Five',
      26 => 'Twenty-Six',
      27 => 'Twenty-Seven',
      28 => 'Twenty-Eight',
      29 => 'Twenty-Nine',
      30 => 'Thirty',
      31 => 'Thirty-One',
      32 => 'Thirty-Two',
      33 => 'Thirty-Three',
      34 => 'Thirty-Four',
      35 => 'Thirty-Five',
      36 => 'Thirty-Six',
      37 => 'Thirty-Seven',
      38 => 'Thirty-Eight',
      39 => 'Thirty-Nine',
      40 => 'Forty',
      41 => 'Forty-One',
      42 => 'Forty-Two',
      43 => 'Forty-Three',
      44 => 'Forty-Four',
      45 => 'Forty-Five',
      // Add more if needed.
    ];
    return isset($words[$number]) ? $words[$number] : (string) $number;
  }

}
