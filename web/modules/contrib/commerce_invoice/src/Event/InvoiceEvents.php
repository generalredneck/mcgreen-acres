<?php

namespace Drupal\commerce_invoice\Event;

final class InvoiceEvents {

  /**
   * Name of the event fired after loading an invoice.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceEvent
   */
  const INVOICE_LOAD = 'commerce_invoice.commerce_invoice.load';

  /**
   * Name of the event fired after creating a new invoice.
   *
   * Fired before the invoice is saved.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceEvent
   */
  const INVOICE_CREATE = 'commerce_invoice.commerce_invoice.create';

  /**
   * Name of the event fired before saving an invoice.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceEvent
   */
  const INVOICE_PRESAVE = 'commerce_invoice.commerce_invoice.presave';

  /**
   * Name of the event fired after saving a new invoice.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceEvent
   */
  const INVOICE_INSERT = 'commerce_invoice.commerce_invoice.insert';

  /**
   * Name of the event fired after saving an existing invoice.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceEvent
   */
  const INVOICE_UPDATE = 'commerce_invoice.commerce_invoice.update';

  /**
   * Name of the event fired before deleting an invoice.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceEvent
   */
  const INVOICE_PREDELETE = 'commerce_invoice.commerce_invoice.predelete';

  /**
   * Name of the event fired after deleting an invoice.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceEvent
   */
  const INVOICE_DELETE = 'commerce_invoice.commerce_invoice.delete';

  /**
   * Name of the event fired after loading an invoice item.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceItemEvent
   */
  const INVOICE_ITEM_LOAD = 'commerce_invoice.commerce_invoice_item.load';

  /**
   * Name of the event fired after creating a new invoice item.
   *
   * Fired before the invoice item is saved.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceItemEvent
   */
  const INVOICE_ITEM_CREATE = 'commerce_invoice.commerce_invoice_item.create';

  /**
   * Name of the event fired before saving an invoice item.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceItemEvent
   */
  const INVOICE_ITEM_PRESAVE = 'commerce_invoice.commerce_invoice_item.presave';

  /**
   * Name of the event fired after saving a new invoice item.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceItemEvent
   */
  const INVOICE_ITEM_INSERT = 'commerce_invoice.commerce_invoice_item.insert';

  /**
   * Name of the event fired after saving an existing invoice item.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceItemEvent
   */
  const INVOICE_ITEM_UPDATE = 'commerce_invoice.commerce_invoice_item.update';

  /**
   * Name of the event fired before deleting an invoice item.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceItemEvent
   */
  const INVOICE_ITEM_PREDELETE = 'commerce_invoice.commerce_invoice_item.predelete';

  /**
   * Name of the event fired after deleting an invoice item.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceItemEvent
   */
  const INVOICE_ITEM_DELETE = 'commerce_invoice.commerce_invoice_item.delete';

  /**
   * Name of the event fired when generating an invoice filename.
   *
   * @Event
   *
   * @see \Drupal\commerce_invoice\Event\InvoiceFilenameEvent
   */
  const INVOICE_FILENAME = 'commerce_invoice.filename';

}
