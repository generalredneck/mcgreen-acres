<?php

namespace Drupal\commerce_invoice\Entity;

use Drupal\commerce\Entity\CommerceBundleEntityInterface;

/**
 * Defines the interface for invoice types.
 */
interface InvoiceTypeInterface extends CommerceBundleEntityInterface {

  /**
   * Gets the invoice type's number pattern.
   *
   * @return \Drupal\commerce_number_pattern\Entity\NumberPatternInterface|null
   *   The invoice type number pattern, NULL if not set.
   */
  public function getNumberPattern();

  /**
   * Gets the invoice type's number pattern ID.
   *
   * @return string
   *   The invoice type number pattern ID.
   */
  public function getNumberPatternId();

  /**
   * Sets the number pattern ID of the invoice type.
   *
   * @param string $number_pattern
   *   The number pattern.
   *
   * @return $this
   */
  public function setNumberPatternId($number_pattern);

  /**
   * Gets the logo file entity.
   *
   * @return \Drupal\file\FileInterface|null
   *   The logo file entity or NULL if it does not exist.
   */
  public function getLogoFile();

  /**
   * Gets the logo URL.
   *
   * @return string|null
   *   The logo URL or NULL if it does not exist.
   */
  public function getLogoUrl();

  /**
   * Sets the logo.
   *
   * @param string $uuid
   *   The UUID of the logo file.
   *
   * @return $this
   */
  public function setLogo($uuid);

  /**
   * Gets the invoice type due days.
   *
   * @return int|null
   *   The invoice type due days.
   */
  public function getDueDays();

  /**
   * Sets the invoice type due days.
   *
   * @param int $days
   *   The due days.
   *
   * @return $this
   */
  public function setDueDays($days);

  /**
   * Gets the invoice type's payment terms.
   *
   * @return string
   *   The invoice type payment terms.
   */
  public function getPaymentTerms();

  /**
   * Sets the payment terms of the invoice type.
   *
   * @param string $payment_terms
   *   The payment terms.
   *
   * @return $this
   */
  public function setPaymentTerms($payment_terms);

  /**
   * Gets the invoice type's footer text.
   *
   * @return string
   *   The invoice type footer text.
   */
  public function getFooterText();

  /**
   * Sets the payment terms of the invoice type.
   *
   * @param string $footer_text
   *   The footer text.
   *
   * @return $this
   */
  public function setFooterText($footer_text);

  /**
   * Gets the invoice type's workflow ID.
   *
   * Used by the $invoice->state field.
   *
   * @return string
   *   The invoice type workflow ID.
   */
  public function getWorkflowId();

  /**
   * Sets the workflow ID of the invoice type.
   *
   * @param string $workflow_id
   *   The workflow ID.
   *
   * @return $this
   */
  public function setWorkflowId($workflow_id);

  /**
   * Gets whether to email the customer a confirmation when an invoice is generated.
   *
   * @return bool
   *   TRUE if the confirmation email should be sent, FALSE otherwise.
   */
  public function shouldSendConfirmation();

  /**
   * Sets whether to email the customer a confirmation when an invoice is generated.
   *
   * @param bool $send_confirmation
   *   TRUE if the confirmation email should be sent, FALSE otherwise.
   *
   * @return $this
   */
  public function setSendConfirmation($send_confirmation);

  /**
   * Gets the confirmation BCC email.
   *
   * If provided, this email will receive a copy of the confirmation email.
   *
   * @return string
   *   The confirmation BCC email.
   */
  public function getConfirmationBcc();

  /**
   * Sets the confirmation BCC email.
   *
   * @param string $confirmation_bcc
   *   The confirmation BCC email.
   *
   * @return $this
   */
  public function setConfirmationBcc($confirmation_bcc);

  /**
   * Gets the configured private subdirectory for storing invoice files.
   *
   * @return string|null
   *   The relative subdirectory within the private file system, or NULL
   *   if files should be stored directly in the root of the private directory.
   */
  public function getPrivateSubdirectory(): ?string;

  /**
   * Sets the private subdirectory for storing invoice files.
   *
   * @param string|null $subdirectory
   *   The relative subdirectory within the private file system,
   *   or NULL to store files directly in the root of the private directory.
   *
   * @return $this
   */
  public function setPrivateSubDirectory(?string $subdirectory): static;

}
