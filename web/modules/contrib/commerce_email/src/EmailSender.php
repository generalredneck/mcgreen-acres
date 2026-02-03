<?php

namespace Drupal\commerce_email;

use Drupal\commerce\MailHandlerInterface;
use Drupal\commerce_email\Entity\EmailInterface;
use Drupal\commerce_log\LogTemplateManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Utility\Token;

/**
 * Email sender service.
 */
class EmailSender implements EmailSenderInterface {

  /**
   * The mail handler.
   *
   * @var \Drupal\commerce\MailHandlerInterface
   */
  protected $mailHandler;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The log template manager.
   *
   * @var \Drupal\commerce_log\LogTemplateManagerInterface
   */
  protected $logTemplateManager;

  /**
   * Constructs a new EmailSender object.
   *
   * @param \Drupal\commerce\MailHandlerInterface $mail_handler
   *   The mail handler.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(MailHandlerInterface $mail_handler, Token $token, Connection $database, EntityTypeManagerInterface $entity_type_manager) {
    $this->mailHandler = $mail_handler;
    $this->token = $token;
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Set the log template manager.
   *
   * @param \Drupal\commerce_log\LogTemplateManagerInterface $log_template_manager
   *   The log template manager.
   */
  public function setLogTemplateManager(LogTemplateManagerInterface $log_template_manager) {
    $this->logTemplateManager = $log_template_manager;
  }

  /**
   * {@inheritDoc}
   */
  public function send(EmailInterface $email, ContentEntityInterface $entity, array $related_entities = []) {
    $entity_type_id = $entity->getEntityTypeId();
    $event_entity_type_id = $email->getEvent()->getEntityTypeId();
    if ($entity_type_id != $event_entity_type_id) {
      throw new \InvalidArgumentException(sprintf('The email requires a "%s" entity, but a "%s" entity was given.', $event_entity_type_id, $entity_type_id));
    }

    // An array of keyed entities used to replace tokens.
    $replacements = [$entity_type_id => $entity];
    if (!empty($related_entities)) {
      $related_entity_type_ids = $email->getEvent()->getRelatedEntityTypeIds();
      $replacements = array_merge($replacements, array_combine($related_entity_type_ids, $related_entities));
    }

    $to = $this->prepareToString($email, $replacements);
    if (empty($to)) {
      return FALSE;
    }
    $subject = $this->replaceTokens($email->getSubject(), $replacements);
    $body = [
      '#theme' => 'commerce_email',
      '#email' => $email,
      '#target_entity' => $entity,
      '#related_entities' => $related_entities,
      '#body' => Markup::create($this->replaceTokens($email->getBody(), $replacements)),
    ];
    // @todo Figure out how to get the langcode generically.
    $params = [
      'id' => 'commerce_email_' . $email->id(),
      'from' => $this->replaceTokens($email->getFrom(), $replacements),
      'cc' => $this->replaceTokens($email->getCc(), $replacements),
      'bcc' => $this->replaceTokens($email->getBcc(), $replacements),
    ];
    $reply_to = $email->getReplyTo();
    if (!empty($reply_to)) {
      $params['reply-to'] = $this->replaceTokens($reply_to, $replacements);
    }

    $result = $this->mailHandler->sendMail($to, $subject, $body, $params);

    // If the email is configured to log sends to the entity.
    if ($email->getLogToEntity() && $this->logTemplateManager !== NULL) {
      // Specify a template ID based on the send result.
      $template_id = $result ? 'mail_' . $email->id() : 'mail_' . $email->id() . '_failure';

      // Look for a matching log template.
      $definitions = $this->logTemplateManager->getDefinitions();
      if (!isset($definitions[$template_id])) {
        // For order emails, fall back to generic templates.
        if ($event_entity_type_id === 'commerce_order') {
          $template_id = $result ? 'order_mail' : 'order_mail_failure';
        }
        else {
          return $result;
        }
      }

      // Log the email to the given entity.
      $log_params = [
        'id' => $email->label(),
        'to_email' => $to,
      ];

      /** @var \Drupal\commerce_log\LogStorageInterface $log_storage */
      $log_storage = $this->entityTypeManager->getStorage('commerce_log');
      $log_storage->generate($entity, $template_id, $log_params)->save();
    }

    return $result;
  }

  /**
   * Replaces tokens in the given value.
   *
   * @param string $value
   *   The value.
   * @param array $replacements
   *   An array of keyed entities.
   *
   * @return string
   *   The value with tokens replaced.
   */
  protected function replaceTokens(string $value, array $replacements) {
    if (!empty($value)) {
      $value = $this->token->replace($value, $replacements, ['clear' => TRUE]);
    }

    return $value;
  }

  /**
   * Prepares value for the 'to' address.
   *
   * @param \Drupal\commerce_email\Entity\EmailInterface $email
   *   The email.
   * @param array $replacements
   *   An array of keyed entities.
   *
   * @return string
   *   Value for the 'to' address.
   */
  protected function prepareToString(EmailInterface $email, array $replacements) {
    if ($email->getToType() === 'role' && !empty($email->getToRole())) {
      $select = $this->database->select('users_field_data', 'd');
      $select->join('user__roles', 'r', 'r.entity_id = d.uid');
      $select->condition('d.status', 1);
      $select->condition('r.roles_target_id', $email->getToRole());
      $select->fields('d', ['mail']);
      $select->groupBy('d.mail');
      $result = $select->execute()->fetchCol();
      $to = implode(', ', $result);
    }
    else {
      $to = $this->replaceTokens($email->getTo(), $replacements);
    }
    return $to;
  }

}
