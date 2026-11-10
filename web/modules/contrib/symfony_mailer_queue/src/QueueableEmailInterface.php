<?php

namespace Drupal\symfony_mailer_queue;

use Symfony\Component\Mime\Email as SymfonyEmail;

/**
 * Interface for queueable emails.
 */
interface QueueableEmailInterface {

  /**
   * Sets the queued flag for the email.
   */
  public function markInQueue(): void;

  /**
   * Gets whether the email is in the queue or not.
   */
  public function isInQueue(): bool;

  /**
   * Sets the original parameters of the email.
   */
  public function setOriginalParams(array $params): static;

  /**
   * Gets the original parameters of the email.
   */
  public function getOriginalParams(): array;

  /**
   * Gets the inner Symfony email object.
   */
  public function getInner(): SymfonyEmail;

  /**
   * Sets the inner Symfony email object.
   */
  public function setInner(SymfonyEmail $email): static;

  /**
   * Gets the base addresses.
   */
  public function getAddresses(): array;

  /**
   * Sets the base addresses.
   */
  public function setAddresses(array $addresses): static;

  /**
   * Gets the whether variables should be replace in the subject.
   */
  public function getSubjectReplace(): bool;

  /**
   * Sets the whether variables should be replace in the subject.
   */
  public function setSubjectReplace(bool $replace): static;

}
