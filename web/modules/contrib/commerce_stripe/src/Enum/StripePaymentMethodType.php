<?php

namespace Drupal\commerce_stripe\Enum;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Maps Stripe payment method type strings to human-readable labels.
 */
enum StripePaymentMethodType: string {

  case AcssDebit = 'acss_debit';
  case Affirm = 'affirm';
  case AfterpayClearpay = 'afterpay_clearpay';
  case Alipay = 'alipay';
  case Alma = 'alma';
  case AmazonPay = 'amazon_pay';
  case AuBecsDebit = 'au_becs_debit';
  case BacsDebit = 'bacs_debit';
  case Bancontact = 'bancontact';
  case Billie = 'billie';
  case Blik = 'blik';
  case Boleto = 'boleto';
  case Card = 'card';
  case CardPresent = 'card_present';
  case Cashapp = 'cashapp';
  case Crypto = 'crypto';
  case CustomerBalance = 'customer_balance';
  case Eps = 'eps';
  case Fpx = 'fpx';
  // Giropay was discontinued June 30, 2024.
  case Grabpay = 'grabpay';
  case Ideal = 'ideal';
  case InteracPresent = 'interac_present';
  case KakaoPay = 'kakao_pay';
  case Klarna = 'klarna';
  case Konbini = 'konbini';
  case KrCard = 'kr_card';
  case Link = 'link';
  case MBWay = 'mb_way';
  case Mobilepay = 'mobilepay';
  case Multibanco = 'multibanco';
  case NaverPay = 'naver_pay';
  case NzBecs = 'nz_bank_account';
  case Oxxo = 'oxxo';
  case P24 = 'p24';
  case PayByBank = 'pay_by_bank';
  case PayCo = 'payco';
  case Paynow = 'paynow';
  case Paypal = 'paypal';
  case Paypay = 'paypay';
  case PayTo = 'payto';
  case Pix = 'pix';
  case Promptpay = 'promptpay';
  case RevolutPay = 'revolut_pay';
  case SamsungPay = 'samsung_pay';
  case Satispay = 'satispay';
  case SepaDebit = 'sepa_debit';
  // Sofort was discontinued March 31, 2025.
  case Sunbit = 'sunbit';
  case Swish = 'swish';
  case Twint = 'twint';
  case Upi = 'upi';
  case UsBankAccount = 'us_bank_account';
  case WechatPay = 'wechat_pay';
  case Zip = 'zip';

  /**
   * Returns a value => label array of all known types.
   *
   * For use as an allowed_values_function callback on list_string fields.
   */
  public static function options(?FieldStorageDefinitionInterface $definition = NULL, ?FieldableEntityInterface $entity = NULL, bool &$cacheable = TRUE): array {
    $options = [];
    foreach (self::cases() as $case) {
      $options[$case->value] = $case->label();
    }
    return $options;
  }

  /**
   * Returns the human-readable label for the payment method type.
   */
  public function label(): string {
    return match($this) {
      self::AcssDebit => 'ACSS Debit',
      self::Affirm => 'Affirm',
      self::AfterpayClearpay => 'Afterpay / Clearpay',
      self::Alipay => 'Alipay',
      self::Alma => 'Alma',
      self::AmazonPay => 'Amazon Pay',
      self::AuBecsDebit => 'BECS Direct Debit',
      self::BacsDebit => 'Bacs Direct Debit',
      self::Bancontact => 'Bancontact',
      self::Billie => 'Billie',
      self::Blik => 'BLIK',
      self::Boleto => 'Boleto',
      self::Card => 'Card',
      self::CardPresent => 'Card Present',
      self::Cashapp => 'Cash App Pay',
      self::Crypto => 'Crypto',
      self::CustomerBalance => 'Customer Balance',
      self::Eps => 'EPS',
      self::Fpx => 'FPX',
      self::Grabpay => 'GrabPay',
      self::Ideal => 'iDEAL',
      self::InteracPresent => 'Interac Present',
      self::KakaoPay => 'Kakao Pay',
      self::Klarna => 'Klarna',
      self::Konbini => 'Konbini',
      self::KrCard => 'Korean Card',
      self::Link => 'Link',
      self::MBWay => 'MB WAY',
      self::Mobilepay => 'MobilePay',
      self::Multibanco => 'Multibanco',
      self::NaverPay => 'Naver Pay',
      self::NzBecs => 'NZ BECS Direct Debit',
      self::Oxxo => 'OXXO',
      self::P24 => 'Przelewy24',
      self::PayByBank => 'Pay By Bank',
      self::PayCo => 'PAYCO',
      self::Paynow => 'PayNow',
      self::Paypal => 'PayPal',
      self::Paypay => 'PayPay',
      self::PayTo => 'PayTo',
      self::Pix => 'Pix',
      self::Promptpay => 'PromptPay',
      self::RevolutPay => 'Revolut Pay',
      self::SamsungPay => 'Samsung Pay',
      self::Satispay => 'Satispay',
      self::SepaDebit => 'SEPA Debit',
      self::Sunbit => 'Sunbit',
      self::Swish => 'Swish',
      self::Twint => 'TWINT',
      self::Upi => 'UPI',
      self::UsBankAccount => 'ACH Direct Debit',
      self::WechatPay => 'WeChat Pay',
      self::Zip => 'Zip',
    };
  }

}
