<?php

namespace Drupal\symfony_mailer_queue;

use Drupal\symfony_mailer\Email;
use Symfony\Component\Mime\Email as SymfonyEmail;

/**
 * Defines the email class for queueable emails.
 */
class QueueableEmail extends Email implements QueueableEmailInterface {

  /**
   * Whether the email is in the queue or not.
   */
  protected bool $isInQueue = FALSE;

  /**
   * The original parameters of the email.
   *
   * The queue worker reinitializes the email using these parameters.
   */
  protected array $originalParams = [];

  /**
   * {@inheritdoc}
   */
  public function markInQueue(): void {
    $this->isInQueue = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isInQueue(): bool {
    return $this->isInQueue;
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalParams(array $params): static {
    $this->originalParams = $params;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalParams(): array {
    return $this->originalParams;
  }

  /**
   * {@inheritdoc}
   */
  public function getInner(): SymfonyEmail {
    return $this->inner;
  }

  /**
   * {@inheritdoc}
   */
  public function setInner(SymfonyEmail $email): static {
    $this->inner = $email;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAddresses(): array {
    return $this->addresses;
  }

  /**
   * {@inheritdoc}
   */
  public function setAddresses(array $addresses): static {
    $this->addresses = $addresses;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubjectReplace(): bool {
    // @phpstan-ignore-next-line Insufficient upstream typing.
    return $this->subjectReplace ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubjectReplace(bool $replace): static {
    $this->subjectReplace = $replace;
    return $this;
  }

}
