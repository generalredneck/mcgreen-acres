<?php

namespace Drupal\commerce_invoice\Entity;

use Drupal\commerce\Entity\CommerceBundleEntityBase;
use Drupal\commerce_number_pattern\Entity\NumberPattern;

/**
 * Defines the invoice type entity class.
 *
 * @ConfigEntityType(
 *   id = "commerce_invoice_type",
 *   label = @Translation("Invoice type"),
 *   label_collection = @Translation("Invoice types"),
 *   label_singular = @Translation("invoice type"),
 *   label_plural = @Translation("invoice types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count invoice type",
 *     plural = "@count invoice types",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\commerce\CommerceBundleAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\commerce_invoice\Form\InvoiceTypeForm",
 *       "duplicate" = "Drupal\commerce_invoice\Form\InvoiceTypeForm",
 *       "edit" = "Drupal\commerce_invoice\Form\InvoiceTypeForm",
 *       "delete" = "Drupal\commerce\Form\CommerceBundleEntityDeleteFormBase"
 *     },
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "list_builder" = "Drupal\commerce_invoice\InvoiceTypeListBuilder",
 *   },
 *   admin_permission = "administer commerce_invoice_type",
 *   config_prefix = "commerce_invoice_type",
 *   bundle_of = "commerce_invoice",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "label",
 *     "id",
 *     "numberPattern",
 *     "logo",
 *     "dueDays",
 *     "paymentTerms",
 *     "footerText",
 *     "traits",
 *     "workflow",
 *     "sendConfirmation",
 *     "confirmationBcc",
 *     "privateSubdirectory",
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/config/invoice-types/add",
 *     "edit-form" = "/admin/commerce/config/invoice-types/{commerce_invoice_type}/edit",
 *     "duplicate-form" = "/admin/commerce/config/invoice-types/{commerce_invoice_type}/duplicate",
 *     "delete-form" = "/admin/commerce/config/invoice-types/{commerce_invoice_type}/delete",
 *     "collection" = "/admin/commerce/config/invoice-types"
 *   }
 * )
 */
class InvoiceType extends CommerceBundleEntityBase implements InvoiceTypeInterface {

  /**
   * The number pattern entity.
   *
   * @var \Drupal\commerce_number_pattern\Entity\NumberPatternInterface
   */
  protected $numberPattern;

  /**
   * UUID of the Invoice type logo file.
   *
   * @var string
   */
  protected $logo;

  /**
   * The invoice type footer text.
   *
   * @var string
   */
  protected $footerText;

  /**
   * The invoice type due days.
   *
   * @var int
   */
  protected $dueDays;

  /**
   * The invoice type payment terms.
   *
   * @var string
   */
  protected $paymentTerms;

  /**
   * The invoice type workflow ID.
   *
   * @var string
   */
  protected $workflow;

  /**
   * Whether to email the customer a confirmation when an invoice is generated.
   *
   * @var bool
   */
  protected $sendConfirmation;

  /**
   * The confirmation BCC email.
   *
   * @var bool
   */
  protected $confirmationBcc;

  /**
   * The configured private subdirectory for storing invoice files.
   *
   * @var string|null
   */
  protected $privateSubdirectory;

  /**
   * {@inheritdoc}
   */
  public function getNumberPattern() {
    if ($this->getNumberPatternId()) {
      return NumberPattern::load($this->getNumberPatternId());
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getNumberPatternId() {
    return $this->numberPattern;
  }

  /**
   * {@inheritdoc}
   */
  public function setNumberPatternId($number_pattern) {
    $this->numberPattern = $number_pattern;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogoFile() {
    if ($this->logo) {
      /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
      $entity_repository = \Drupal::service('entity.repository');
      return $entity_repository->loadEntityByUuid('file', $this->logo);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogoUrl() {
    if ($image = $this->getLogoFile()) {
      return $image->createFileUrl(FALSE);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setLogo($uuid) {
    $this->logo = $uuid;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDueDays() {
    return $this->dueDays;
  }

  /**
   * {@inheritdoc}
   */
  public function setDueDays($days) {
    $this->dueDays = $days;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentTerms() {
    return $this->paymentTerms;
  }

  /**
   * {@inheritdoc}
   */
  public function setPaymentTerms($payment_terms) {
    $this->paymentTerms = $payment_terms;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFooterText() {
    return $this->footerText;
  }

  /**
   * {@inheritdoc}
   */
  public function setFooterText($footer_text) {
    $this->footerText = $footer_text;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowId() {
    return $this->workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function setWorkflowId($workflow_id) {
    $this->workflow = $workflow_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldSendConfirmation() {
    return $this->sendConfirmation;
  }

  /**
   * {@inheritdoc}
   */
  public function setSendConfirmation($send_confirmation) {
    $this->sendConfirmation = (bool) $send_confirmation;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmationBcc() {
    return $this->confirmationBcc;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfirmationBcc($confirmation_bcc) {
    $this->confirmationBcc = $confirmation_bcc;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrivateSubdirectory(): ?string {
    return $this->privateSubdirectory;
  }

  /**
   * {@inheritdoc}
   */
  public function setPrivateSubdirectory(?string $subdirectory): static {
    $this->privateSubdirectory = $subdirectory;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // The invoice type must depend on the module that provides the workflow.
    $workflow_manager = \Drupal::service('plugin.manager.workflow');
    $workflow = $workflow_manager->createInstance($this->getWorkflowId());
    $this->calculatePluginDependencies($workflow);

    // Add the logo entity as dependency if a UUID was specified.
    if ($this->logo && $file = $this->getLogoFile()) {
      $this->addDependency($file->getConfigDependencyKey(), $file->getConfigDependencyName());
    }

    return $this;
  }

}
