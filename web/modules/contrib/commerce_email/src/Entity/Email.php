<?php

namespace Drupal\commerce_email\Entity;

use Drupal\commerce\ConditionGroup;
use Drupal\commerce\Plugin\Commerce\Condition\ParentEntityAwareInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the email entity class.
 *
 * @ConfigEntityType(
 *   id = "commerce_email",
 *   label = @Translation("Email"),
 *   label_collection = @Translation("Emails"),
 *   label_singular = @Translation("email"),
 *   label_plural = @Translation("emails"),
 *   label_count = @PluralTranslation(
 *     singular = "@count email",
 *     plural = "@count emails",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\commerce_email\Form\EmailForm",
 *       "duplicate" = "Drupal\commerce_email\Form\EmailForm",
 *       "edit" = "Drupal\commerce_email\Form\EmailForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "list_builder" = "Drupal\commerce_email\EmailListBuilder",
 *   },
 *   admin_permission = "administer commerce_email",
 *   config_prefix = "commerce_email",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "event",
 *     "targetEntityType",
 *     "from",
 *     "toType",
 *     "toRole",
 *     "to",
 *     "cc",
 *     "bcc",
 *     "replyTo",
 *     "subject",
 *     "body",
 *     "logToEntity",
 *     "queue",
 *     "conditions",
 *     "conditionOperator",
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/config/emails/add",
 *     "edit-form" = "/admin/commerce/config/emails/{commerce_email}/edit",
 *     "duplicate-form" = "/admin/commerce/config/emails/{commerce_email}/duplicate",
 *     "delete-form" = "/admin/commerce/config/emails/{commerce_email}/delete",
 *     "collection" = "/admin/commerce/config/emails"
 *   }
 * )
 */
class Email extends ConfigEntityBase implements EmailInterface {

  /**
   * The email ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The email label.
   *
   * @var string
   */
  protected $label;

  /**
   * The email event.
   *
   * @var string
   */
  protected $event;

  /**
   * The target entity type ID.
   *
   * @var string
   */
  protected $targetEntityType;

  /**
   * The "from" address.
   *
   * @var string
   */
  protected $from;

  /**
   * The 'toType' option.
   *
   * @var string
   */
  protected $toType;

  /**
   * The 'toRole' value.
   *
   * @var string
   */
  protected $toRole;

  /**
   * The "to" address.
   *
   * @var string
   */
  protected $to;

  /**
   * The "CC" address.
   *
   * @var string
   */
  protected $cc;

  /**
   * The "BCC" address.
   *
   * @var string
   */
  protected $bcc;

  /**
   * The "reply-to" address.
   *
   * @var string
   */
  protected $replyTo;

  /**
   * The subject.
   *
   * @var string
   */
  protected $subject;

  /**
   * The body.
   *
   * @var array
   */
  protected $body = [];

  /**
   * The conditions.
   *
   * @var array
   */
  protected $conditions = [];

  /**
   * The condition operator.
   *
   * @var string
   */
  protected $conditionOperator = 'AND';

  /**
   * Whether or not the email should be queued for sending.
   *
   * @var bool
   */
  protected $queue;

  /**
   * Whether or not sends should be logged to the entity.
   *
   * @var bool
   */
  protected $logToEntity;

  /**
   * {@inheritdoc}
   */
  public function getEvent() {
    if ($this->event) {
      /** @var \Drupal\Core\Plugin\DefaultPluginManager $email_event_manager */
      $email_event_manager = \Drupal::service('plugin.manager.commerce_email_event');
      return $email_event_manager->createInstance($this->event);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEventId() {
    return $this->event;
  }

  /**
   * {@inheritdoc}
   */
  public function setEventId($event_id) {
    $this->event = $event_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId() {
    return $this->targetEntityType;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetEntityTypeId($entity_type_id) {
    $this->targetEntityType = $entity_type_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFrom() {
    return $this->from;
  }

  /**
   * {@inheritdoc}
   */
  public function setFrom($from) {
    $this->from = $from;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getToType() {
    return $this->toType;
  }

  /**
   * {@inheritdoc}
   */
  public function setToType($to_type) {
    $this->toType = $to_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getToRole() {
    return $this->toRole;
  }

  /**
   * {@inheritdoc}
   */
  public function setToRole($to_role) {
    $this->toRole = $to_role;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTo() {
    return $this->to;
  }

  /**
   * {@inheritdoc}
   */
  public function setTo($to) {
    $this->to = $to;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCc() {
    return $this->cc;
  }

  /**
   * {@inheritdoc}
   */
  public function setCc($cc) {
    $this->cc = $cc;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBcc() {
    return $this->bcc;
  }

  /**
   * {@inheritdoc}
   */
  public function setBcc($bcc) {
    $this->bcc = $bcc;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getReplyTo() {
    return $this->replyTo;
  }

  /**
   * {@inheritdoc}
   */
  public function setReplyTo($reply_to) {
    $this->replyTo = $reply_to;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubject() {
    return $this->subject;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubject($subject) {
    $this->subject = $subject;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBody() {
    if (is_array($this->body)) {
      return $this->body['value'] ?? '';
    }
    return $this->body;
  }

  /**
   * {@inheritdoc}
   */
  public function setBody($body) {
    return $this->body = $body;
  }

  /**
   * {@inheritdoc}
   */
  public function getBodyFormat() {
    $default_format = 'basic_html';
    if (is_array($this->body)) {
      return $this->body['format'] ?? $default_format;
    }
    return $default_format;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldQueue() {
    return $this->queue;
  }

  /**
   * {@inheritdoc}
   */
  public function setQueue($queue) {
    $this->queue = (bool) $queue;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConditions() {
    $plugin_manager = \Drupal::service('plugin.manager.commerce_condition');
    $conditions = [];
    foreach ($this->conditions as $condition) {
      $condition = $plugin_manager->createInstance($condition['plugin'], $condition['configuration']);
      if ($condition instanceof ParentEntityAwareInterface) {
        $condition->setParentEntity($this);
      }
      $conditions[] = $condition;
    }
    return $conditions;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogToEntity() {
    return $this->logToEntity;
  }

  /**
   * {@inheritdoc}
   */
  public function setLogToEntity($value) {
    $this->logToEntity = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConditionOperator() {
    return $this->conditionOperator;
  }

  /**
   * {@inheritdoc}
   */
  public function setConditionOperator($condition_operator) {
    $this->conditionOperator = $condition_operator;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(ContentEntityInterface $entity) {
    $conditions = $this->getConditions();
    if (!$conditions) {
      // Emails without conditions always apply.
      return TRUE;
    }
    $condition_group = new ConditionGroup($conditions, $this->getConditionOperator());

    return $condition_group->evaluate($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Populate the target entity type ID from the event.
    if ($this->event && !$this->targetEntityType) {
      $this->targetEntityType = $this->getEvent()->getEntityTypeId();
    }
  }

}
