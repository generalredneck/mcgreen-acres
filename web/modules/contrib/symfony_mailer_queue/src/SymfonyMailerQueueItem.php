<?php

namespace Drupal\symfony_mailer_queue;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\symfony_mailer\AddressInterface;
use Symfony\Component\Mime\Email;

/**
 * Provides a data transfer object for Symfony mailer queue items.
 *
 * @internal
 */
class SymfonyMailerQueueItem {

  /**
   * A unique identifier for this queue item.
   *
   * The unique identifier is necessary to make this queue item identifiable
   * after serialization. Unfortunately, the queue worker only receives the
   * queue item data and not the queue item ID. In the hypothetical scenario
   * that multiple identical emails are send out at once having non-unique
   * items would cause conflicts.
   */
  protected string $id;

  /**
   * Constructs a new SymfonyMailerQueueItem object.
   *
   * @param string $type
   *   The email type.
   * @param string $subType
   *   The email sub-type.
   * @param array $params
   *   An array of parameters for building the email.
   * @param array|null $variables
   *   An array of variables available in the email template.
   * @param \Symfony\Component\Mime\Email|null $inner
   *   The inner Symfony email object.
   * @param array|null $addresses
   *   An array of email addresses.
   * @param \Drupal\symfony_mailer\AddressInterface|null $sender
   *   The sender address.
   * @param \Drupal\Component\Render\MarkupInterface|string|null $subject
   *   The email subject.
   * @param bool|null $subjectReplace
   *   Whether to replace the subject.
   * @param array|null $body
   *   The email body.
   * @param string|null $theme
   *   The theme to use for rendering the email.
   * @param string|null $transportDsn
   *   The transport DSN.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface|null $entity
   *   The associated config entity or NULL if no associated config entity.
   * @param string|null $langcode
   *   The current langcode.
   * @param array $config
   *   An array of configuration options specified with the email adjuster.
   *
   * @see \Drupal\symfony_mailer\EmailInterface
   */
  public function __construct(
    public readonly string $type,
    public readonly string $subType,
    public readonly array $params = [],
    public readonly ?array $variables = [],
    public readonly ?Email $inner = NULL,
    public readonly ?array $addresses = [],
    public readonly ?AddressInterface $sender = NULL,
    public readonly MarkupInterface|string|null $subject = '',
    public readonly ?bool $subjectReplace = FALSE,
    public readonly ?array $body = [],
    public readonly ?string $theme = NULL,
    public readonly ?string $transportDsn = '',
    public readonly ?ConfigEntityInterface $entity = NULL,
    public readonly ?string $langcode = '',
    public readonly array $config = [],
  ) {
    $this->id = uniqid('', TRUE);
  }

}
