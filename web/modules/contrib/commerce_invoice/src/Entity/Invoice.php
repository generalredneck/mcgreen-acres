<?php

namespace Drupal\commerce_invoice\Entity;

use Drupal\commerce\Entity\CommerceContentEntityBase;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_price\Price;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the invoice entity class.
 *
 * @ContentEntityType(
 *   id = "commerce_invoice",
 *   label = @Translation("Invoice"),
 *   label_collection = @Translation("Invoices"),
 *   label_singular = @Translation("invoice"),
 *   label_plural = @Translation("invoices"),
 *   label_count = @PluralTranslation(
 *     singular = "@count invoice",
 *     plural = "@count invoices",
 *   ),
 *   bundle_label = @Translation("Invoice type"),
 *   handlers = {
 *     "event" = "Drupal\commerce_invoice\Event\InvoiceEvent",
 *     "storage" = "Drupal\commerce_invoice\InvoiceStorage",
 *     "access" = "Drupal\commerce_invoice\InvoiceAccessControlHandler",
 *     "list_builder" = "Drupal\commerce_invoice\InvoiceListBuilder",
 *     "permission_provider" = "Drupal\commerce_invoice\InvoicePermissionProvider",
 *     "views_data" = "Drupal\commerce\CommerceEntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\commerce_invoice\Form\InvoiceForm",
 *       "delete" = "Drupal\commerce_invoice\Form\InvoiceDeleteForm",
 *       "resend-confirmation" = "Drupal\commerce_invoice\Form\InvoiceConfirmationResendForm",
 *     },
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\commerce_invoice\InvoiceRouteProvider",
 *     },
 *     "translation" = "Drupal\content_translation\ContentTranslationHandler"
 *   },
 *   translatable = TRUE,
 *   base_table = "commerce_invoice",
 *   data_table = "commerce_invoice_field_data",
 *   admin_permission = "administer commerce_invoice",
 *   permission_granularity = "bundle",
 *   entity_keys = {
 *     "id" = "invoice_id",
 *     "label" = "invoice_number",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *     "bundle" = "type",
 *   },
 *   links = {
 *     "canonical" = "/admin/commerce/invoices/{commerce_invoice}",
 *     "user-view" = "/user/{user}/invoices/{commerce_invoice}",
 *     "collection" = "/admin/commerce/invoices",
 *     "download" = "/invoice/{commerce_invoice}/download",
 *     "delete-form" = "/admin/commerce/invoices/{commerce_invoice}/delete",
 *     "payment-form" = "/admin/commerce/invoices/{commerce_invoice}/payment",
 *     "resend-confirmation-form" = "/admin/commerce/invoices/{commerce_invoice}/resend-confirmation",
 *   },
 *   bundle_entity_type = "commerce_invoice_type",
 *   field_ui_base_route = "entity.commerce_invoice_type.edit_form",
 *   allow_number_patterns = TRUE,
 * )
 */
class Invoice extends CommerceContentEntityBase implements InvoiceInterface {

  use EntityChangedTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $label = parent::label();
    $invoice_type = InvoiceType::load($this->bundle());
    if ($invoice_type) {
      $label = $this->t('@invoice_type #@invoice_number', [
        '@invoice_type' => $invoice_type->label(),
        '@invoice_number' => $this->getInvoiceNumber() ?? $this->id(),
      ]);
    }

    return $label;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    if ($rel === 'user-view') {
      $uri_route_parameters['user'] = $this->getCustomerId();
    }
    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    // For better UX, the collection URL of invoices that are created for a
    // single order should be the list of invoices for that specific order.
    if ($rel === 'collection' && count($this->get('orders')) === 1) {
      $order = $this->get('orders')->first()->entity;
      if ($order) {
        if ($this->bundle() === 'default') {
          return $order->toUrl('invoices', $options);
        }
        elseif ($this->bundle() === 'credit_memo') {
          return $order->toUrl('credit-memos', $options);
        }
      }
    }

    return parent::toUrl($rel, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceNumber() {
    return $this->get('invoice_number')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setInvoiceNumber($invoice_number) {
    $this->set('invoice_number', $invoice_number);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStore() {
    return $this->getTranslatedReferencedEntity('store_id');
  }

  /**
   * {@inheritdoc}
   */
  public function setStore(StoreInterface $store) {
    $this->set('store_id', $store->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStoreId() {
    return $this->get('store_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setStoreId($store_id) {
    $this->set('store_id', $store_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomer() {
    $customer = $this->get('uid')->entity;
    // Handle deleted customers.
    if (!$customer) {
      $customer = User::getAnonymousUser();
    }
    return $customer;
  }

  /**
   * {@inheritdoc}
   */
  public function setCustomer(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setCustomerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail() {
    return $this->get('mail')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail($mail) {
    $this->set('mail', $mail);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBillingProfile() {
    return $this->get('billing_profile')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setBillingProfile(ProfileInterface $profile) {
    $this->set('billing_profile', $profile);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrders() {
    return $this->get('orders')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function setOrders(array $orders) {
    $this->set('orders', $orders);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getItems() {
    return $this->getTranslatedReferencedEntities('invoice_items');
  }

  /**
   * {@inheritdoc}
   */
  public function setItems(array $invoice_items) {
    $this->set('invoice_items', $invoice_items);
    $this->recalculateTotalPrice();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasItems() {
    return !$this->get('invoice_items')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function addItem(InvoiceItemInterface $invoice_item) {
    $this->get('invoice_items')->appendItem($invoice_item);
    $this->recalculateTotalPrice();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem(InvoiceItemInterface $invoice_item) {
    $index = $this->getItemIndex($invoice_item);
    if ($index !== FALSE) {
      $this->get('invoice_items')->offsetUnset($index);
      $this->recalculateTotalPrice();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasItem(InvoiceItemInterface $invoice_item) {
    return $this->getItemIndex($invoice_item) !== FALSE;
  }

  /**
   * Gets the index of the given invoice item.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceItemInterface $invoice_item
   *   The invoice item.
   *
   * @return int|bool
   *   The index of the given invoice item, or FALSE if not found.
   */
  protected function getItemIndex(InvoiceItemInterface $invoice_item) {
    $values = $this->get('invoice_items')->getValue();
    $invoice_item_ids = array_map(function ($value) {
      return $value['target_id'];
    }, $values);

    return array_search($invoice_item->id(), $invoice_item_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getAdjustments(array $adjustment_types = []) {
    /** @var \Drupal\commerce_order\Adjustment[] $adjustments */
    $adjustments = $this->get('adjustments')->getAdjustments();
    // Filter adjustments by type, if needed.
    if ($adjustment_types) {
      foreach ($adjustments as $index => $adjustment) {
        if (!in_array($adjustment->getType(), $adjustment_types)) {
          unset($adjustments[$index]);
        }
      }
      $adjustments = array_values($adjustments);
    }

    return $adjustments;
  }

  /**
   * {@inheritdoc}
   */
  public function setAdjustments(array $adjustments) {
    $this->set('adjustments', $adjustments);
    $this->recalculateTotalPrice();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addAdjustment(Adjustment $adjustment) {
    $this->get('adjustments')->appendItem($adjustment);
    $this->recalculateTotalPrice();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeAdjustment(Adjustment $adjustment) {
    $this->get('adjustments')->removeAdjustment($adjustment);
    $this->recalculateTotalPrice();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function collectAdjustments(array $adjustment_types = []) {
    $adjustments = [];
    foreach ($this->getItems() as $invoice_item) {
      foreach ($invoice_item->getAdjustments($adjustment_types) as $adjustment) {
        $adjustments[] = $adjustment;
      }
    }
    foreach ($this->getAdjustments($adjustment_types) as $adjustment) {
      $adjustments[] = $adjustment;
    }

    return $adjustments;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethod() {
    return $this->get('payment_method')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPaymentMethod($payment_method) {
    $this->set('payment_method', $payment_method);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubtotalPrice() {
    /** @var \Drupal\commerce_price\Price $subtotal_price */
    $subtotal_price = NULL;
    foreach ($this->getItems() as $invoice_item) {
      if ($invoice_item_total = $invoice_item->getTotalPrice()) {
        $subtotal_price = $subtotal_price ? $subtotal_price->add($invoice_item_total) : $invoice_item_total;
      }
    }
    return $subtotal_price;
  }

  /**
   * {@inheritdoc}
   */
  public function recalculateTotalPrice() {
    /** @var \Drupal\commerce_price\Price $total_price */
    $total_price = NULL;
    foreach ($this->getItems() as $invoice_item) {
      if ($invoice_item_total = $invoice_item->getTotalPrice()) {
        $total_price = $total_price ? $total_price->add($invoice_item_total) : $invoice_item_total;
      }
    }
    if ($total_price) {
      $adjustments = $this->collectAdjustments();
      if ($adjustments) {
        /** @var \Drupal\commerce_order\AdjustmentTransformerInterface $adjustment_transformer */
        $adjustment_transformer = \Drupal::service('commerce_order.adjustment_transformer');
        $adjustments = $adjustment_transformer->combineAdjustments($adjustments);
        $adjustments = $adjustment_transformer->roundAdjustments($adjustments);
        foreach ($adjustments as $adjustment) {
          if (!$adjustment->isIncluded()) {
            $total_price = $total_price->add($adjustment->getAmount());
          }
        }
      }
    }
    $this->total_price = $total_price;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalPrice() {
    if (!$this->get('total_price')->isEmpty()) {
      return $this->get('total_price')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalPaid() {
    if (!$this->get('total_paid')->isEmpty()) {
      return $this->get('total_paid')->first()->toPrice();
    }
    elseif ($total_price = $this->getTotalPrice()) {
      return new Price('0', $total_price->getCurrencyCode());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setTotalPaid(Price $total_paid) {
    $this->set('total_paid', $total_paid);
  }

  /**
   * {@inheritdoc}
   */
  public function getBalance() {
    if ($total_price = $this->getTotalPrice()) {
      return $total_price->subtract($this->getTotalPaid());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isPaid() {
    $total_price = $this->getTotalPrice();
    if (!$total_price) {
      return FALSE;
    }

    $balance = $this->getBalance();
    return $balance->isNegative() || $balance->isZero();
  }

  /**
   * {@inheritdoc}
   */
  public function getState() {
    return $this->get('state')->first();
  }

  /**
   * {@inheritdoc}
   */
  public function getData($key, $default = NULL) {
    $data = [];
    if (!$this->get('data')->isEmpty()) {
      $data = $this->get('data')->first()->getValue();
    }
    return $data[$key] ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setData($key, $value) {
    $this->get('data')->__set($key, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unsetData($key) {
    if (!$this->get('data')->isEmpty()) {
      $data = $this->get('data')->first()->getValue();
      unset($data[$key]);
      $this->set('data', $data);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceDateTime() {
    return $this->get('invoice_date')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setInvoiceDateTime($timestamp) {
    $this->set('invoice_date', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDueDateTime() {
    return $this->get('due_date')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDueDateTime($timestamp) {
    $this->set('due_date', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return $this->get('invoice_file')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setFile(FileInterface $file) {
    $this->set('invoice_file', $file->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (['store_id'] as $field) {
      if ($this->get($field)->isEmpty()) {
        throw new EntityMalformedException(sprintf('Required invoice field "%s" is empty.', $field));
      }
    }
    // Store the original override language to be able to put it back.
    $original_language = $this->languageManager()->getConfigOverrideLanguage();

    // Store the invoice type data in the invoice data for immutability reasons.
    $fields_whitelist = ['paymentTerms', 'footerText', 'logo'];
    $fields_whitelist = array_combine($fields_whitelist, $fields_whitelist);
    // The following code is necessary to store the translated invoice type
    // data for each translation.
    foreach ($this->getTranslationLanguages() as $langcode => $language) {
      $translated_invoice = $this->getTranslation($langcode);
      if (!$translated_invoice->getData('invoice_type', FALSE)) {
        $this->languageManager()->setConfigOverrideLanguage($language);
        $invoice_type = InvoiceType::load($this->bundle());
        $invoice_type_data = $invoice_type->toArray();
        // Store in the data array the following invoice type fields.
        $invoice_type_data = array_filter(array_intersect_key($invoice_type_data, $fields_whitelist));
        if ($invoice_type_data) {
          $translated_invoice->setData('invoice_type', $invoice_type_data);
        }
      }
    }
    $this->languageManager()->setConfigOverrideLanguage($original_language);
    $invoice_type = InvoiceType::load($this->bundle());

    // Skip generating an invoice number for draft invoices.
    if ($this->getState()->getId() != 'draft' && empty($this->getInvoiceNumber())) {
      /** @var \Drupal\commerce_number_pattern\Entity\NumberPatternInterface $number_pattern */
      $number_pattern = $invoice_type->getNumberPattern();
      if ($number_pattern) {
        $invoice_number = $number_pattern->getPlugin()->generate($this);
        $this->setInvoiceNumber($invoice_number);
      }
    }

    $customer = $this->getCustomer();
    // The customer has been deleted, clear the reference.
    if ($this->getCustomerId() && $customer->isAnonymous()) {
      $this->setCustomerId(0);
    }
    // Make sure the billing profile is owned by the invoice, not the customer.
    $billing_profile = $this->getBillingProfile();
    if ($billing_profile && $billing_profile->getOwnerId()) {
      $billing_profile->setOwnerId(0);
      $billing_profile->save();
    }

    if (empty($this->getInvoiceDateTime())) {
      $this->setInvoiceDateTime(\Drupal::time()->getRequestTime());
    }

    // Calculate the due date if not set and if configured to do so on the
    // invoice type.
    if ($this->isNew() && empty($this->getDueDateTime()) && is_numeric($invoice_type->getDueDays())) {
      $invoice_date = DrupalDateTime::createFromTimestamp($this->getInvoiceDateTime());
      $due_date = $invoice_date->modify("+{$invoice_type->getDueDays()} days");
      $this->setDueDateTime($due_date->getTimestamp());
    }

    // When the invoice state is updated, clear the invoice file reference.
    // (A "paid" invoice probably looks different than a "pending" invoice).
    // That'll force the invoice file manager to regenerate an invoice PDF
    // the next time it's called.
    $original_state = isset($this->original) ? $this->original->getState()->getId() : '';
    if (($original_state && $original_state !== $this->getState()->getId()) && !empty($this->getFile())) {
      $this->set('invoice_file', NULL);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Ensure there's a back-reference on each invoice item.
    foreach ($this->getItems() as $invoice_item) {
      if ($invoice_item->invoice_id->isEmpty()) {
        $invoice_item->invoice_id = $this->id();
        $invoice_item->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    // Delete the invoice items of a deleted invoice.
    $invoice_items = [];
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $entity */
    foreach ($entities as $entity) {
      foreach ($entity->getItems() as $invoice_item) {
        $invoice_items[$invoice_item->id()] = $invoice_item;
      }
    }
    if (!$invoice_items) {
      return;
    }
    $invoice_item_storage = \Drupal::service('entity_type.manager')->getStorage('commerce_invoice_item');
    $invoice_item_storage->delete($invoice_items);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['invoice_number'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Invoice number'))
      ->setDescription(t('The invoice number.'))
      ->setRequired(TRUE)
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['store_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Store'))
      ->setDescription(t('The store to which the invoice belongs.'))
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setSetting('target_type', 'commerce_store')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Customer'))
      ->setDescription(t('The customer.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\commerce_invoice\Entity\Invoice::getCurrentUserId')
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['mail'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Contact email'))
      ->setDescription(t('The email address associated with the invoice.'))
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['billing_profile'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setLabel(t('Billing information'))
      ->setDescription(t('Billing profile'))
      ->setSetting('target_type', 'profile')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', ['target_bundles' => ['customer']])
      ->setDisplayConfigurable('view', TRUE);

    $fields['orders'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Orders'))
      ->setDescription(t('The invoice orders.'))
      ->setRequired(TRUE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'commerce_order')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('view', TRUE);

    $fields['invoice_items'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Invoice items'))
      ->setDescription(t('The invoice items.'))
      ->setRequired(TRUE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'commerce_invoice_item')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
        'weight' => 0,
        'settings' => [
          'allow_new' => FALSE,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'commerce_invoice_item_table',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['adjustments'] = BaseFieldDefinition::create('commerce_adjustment')
      ->setLabel(t('Adjustments'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'commerce_adjustment_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['payment_method'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Payment method'))
      ->setDescription(t('The payment method.'))
      ->setRequired(TRUE)
      ->setDefaultValue('')
      ->setSetting('max_length', 255);

    $fields['total_price'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Total price'))
      ->setDescription(t('The total price of the invoice.'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'commerce_invoice_total_summary',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['total_paid'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Total paid'))
      ->setDescription(t('The total paid price of the invoice.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['state'] = BaseFieldDefinition::create('state')
      ->setLabel(t('State'))
      ->setDescription(t('The invoice state.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'state_transition_form',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setSetting('workflow_callback', ['\Drupal\commerce_invoice\Entity\Invoice', 'getWorkflowId']);

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setTranslatable(TRUE)
      ->setDescription(t('A serialized array of additional data.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the invoice was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the invoice was last edited.'));

    $fields['invoice_date'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Invoice date'))
      ->setDescription(t('The invoice date'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['due_date'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Due date'))
      ->setDescription(t('The date the invoice is due.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['invoice_file'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Invoice PDF'))
      ->setDescription(t('The invoice PDF file.'))
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * Gets the workflow ID for the state field.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice
   *   The invoice.
   *
   * @return string
   *   The workflow ID.
   */
  public static function getWorkflowId(InvoiceInterface $invoice) {
    return InvoiceType::load($invoice->bundle())->getWorkflowId();
  }

}
