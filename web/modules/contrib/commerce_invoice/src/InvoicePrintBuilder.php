<?php

namespace Drupal\commerce_invoice;

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\commerce_invoice\Entity\InvoiceType;
use Drupal\commerce_invoice\Event\InvoiceEvents;
use Drupal\commerce_invoice\Event\InvoiceFilenameEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\entity_print\FilenameGeneratorInterface;
use Drupal\entity_print\Plugin\PrintEngineInterface;
use Drupal\entity_print\PrintBuilderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The print builder service.
 */
class InvoicePrintBuilder implements InvoicePrintBuilderInterface {

  use StringTranslationTrait;

  /**
   * The entity storage for the 'file' entity type.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $fileStorage;

  /**
   * Constructs a new InvoicePrintBuilder object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\entity_print\PrintBuilderInterface $printBuilder
   *   The Entity print builder.
   * @param \Drupal\entity_print\FilenameGeneratorInterface $filenameGenerator
   *   The Entity print filename generator.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Language\LanguageDefault $languageDefault
   *   The language default.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager,
    protected PrintBuilderInterface $printBuilder,
    protected FilenameGeneratorInterface $filenameGenerator,
    protected EventDispatcherInterface $eventDispatcher,
    protected AccountInterface $currentUser,
    protected LanguageDefault $languageDefault,
    protected LanguageManagerInterface $languageManager,
  ) {
    $this->fileStorage = $entityTypeManager->getStorage('file');
  }

  /**
   * {@inheritdoc}
   */
  public function generateFilename(InvoiceInterface $invoice) {
    // Define our own label callback for BC reasons as we historically did
    // not include the invoice type in the filename which is now returned by
    // the invoice label() method.
    $filename = $this->filenameGenerator->generateFilename([$invoice], static function (InvoiceInterface $invoice) {
      return $invoice->getInvoiceNumber();
    });
    $filename .= '-' . $invoice->language()->getId() . '-' . str_replace('_', '', $invoice->getState()->getId());
    // Let the filename be altered.
    $event = new InvoiceFilenameEvent($filename, $invoice);
    $this->eventDispatcher->dispatch($event, InvoiceEvents::INVOICE_FILENAME);
    $filename = $event->getFilename() . '.pdf';
    return $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function savePrintable(InvoiceInterface $invoice, PrintEngineInterface $print_engine, $scheme = 'private') {
    // Determines whether the active language needs to be changed.
    $current_langcode = $this->languageManager->getCurrentLanguage()->getId();
    $invoice_langcode = $invoice->language()->getId();

    // Change the active language so the pdf is properly translated.
    if ($invoice_langcode != $current_langcode) {
      $this->changeActiveLanguage($invoice_langcode);
    }

    $filename = $this->generateFilename($invoice);
    $config = $this->configFactory->get('entity_print.settings');
    // Save the file to the private subdirectory if the invoice type has one
    // specified.
    $invoice_type = InvoiceType::load($invoice->bundle());
    if ($invoice_type?->getPrivateSubdirectory()) {
      $filename = $invoice_type->getPrivateSubdirectory() . '/' . $filename;
    }
    $uri = $this->printBuilder->savePrintable([$invoice], $print_engine, $scheme, $filename, $config->get('default_css'));

    // Revert back to the original active language.
    if ($invoice_langcode != $current_langcode) {
      $this->changeActiveLanguage($current_langcode);
    }

    if (!$uri) {
      return FALSE;
    }

    /** @var \Drupal\file\FileInterface $file */
    $file = $this->fileStorage->create([
      'uri' => $uri,
      'uid' => $this->currentUser->id(),
      'langcode' => $invoice->language()->getId(),
    ]);
    $file->setPermanent();
    $file->save();

    return $file;
  }

  /**
   * Changes the active language for translations.
   *
   * @param string $langcode
   *   The langcode.
   */
  protected function changeActiveLanguage(string $langcode) {
    if (!$this->languageManager->isMultilingual()) {
      return;
    }
    $language = $this->languageManager->getLanguage($langcode);
    if (!$language) {
      return;
    }
    // The language manager has no method for overriding the default
    // language, like it does for config overrides. We have to change the
    // default language service's current language.
    // @see https://www.drupal.org/project/drupal/issues/3029010
    $this->languageDefault->set($language);
    $this->languageManager->setConfigOverrideLanguage($language);
    $this->languageManager->reset();

    // The default string_translation service, TranslationManager, has a
    // setDefaultLangcode method. However, this method is not present on
    // either of its interfaces. Therefore we check for the concrete class
    // here so that any swapped service does not break the application.
    // @see https://www.drupal.org/project/drupal/issues/3029003
    $string_translation = $this->getStringTranslation();
    if ($string_translation instanceof TranslationManager) {
      $string_translation->setDefaultLangcode($language->getId());
      $string_translation->reset();
    }
  }

}
