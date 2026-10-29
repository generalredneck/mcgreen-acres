<?php

namespace Drupal\media_bulk_upload\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StreamWrapper\StreamWrapperManager;

/**
 * Builds form to edit Media Bulk Config entities.
 */
class MediaBulkConfigForm extends EntityForm implements ContainerInjectionInterface {

  /**
   * Entity Display Repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * File System Interface.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Entity Stream Wrapper Manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManager
   */
  protected $streamWrapperManager;

  /**
   * Constructs a new MediaBulkConfigForm.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   Entity Display repository.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Files system service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManager $streamWrapperManager
   *   Stream wrapper service.
   */
  public function __construct(
    EntityDisplayRepositoryInterface $entityDisplayRepository,
    MessengerInterface $messenger,
    FileSystemInterface $fileSystem,
    StreamWrapperManager $streamWrapperManager,
  ) {
    $this->entityDisplayRepository = $entityDisplayRepository;
    $this->messenger = $messenger;
    $this->fileSystem = $fileSystem;
    $this->streamWrapperManager = $streamWrapperManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_display.repository'),
      $container->get('messenger'),
      $container->get('file_system'),
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $mediaTypeStorage = $this->entityTypeManager->getStorage('media_type');
    $mediaTypes = $mediaTypeStorage->loadMultiple();

    if (empty($mediaTypes)) {
      $this->messenger->addWarning($this->t(
        '<em>Media Bulk Upload</em> requires media types to be configured. No media types have been found. Please add a media type first (image, document, etc).'
      ));

      $response = new RedirectResponse(Url::fromRoute('entity.media_type.collection')->toString());
      $response->send();
    }

    /** @var \Drupal\media_bulk_upload\Entity\MediaBulkConfigInterface $mediaBulkConfig */
    $mediaBulkConfig = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $mediaBulkConfig->label(),
      '#description' => $this->t("Label for the media bulk upload settings, for example 'Bulk image upload'"),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $mediaBulkConfig->id(),
      '#machine_name' => [
        'exists' => '\Drupal\media_bulk_upload\Entity\MediaBulkConfig::load',
      ],
      '#disabled' => !$mediaBulkConfig->isNew(),
    ];

    $media_types = $mediaBulkConfig->get('media_types');
    $form['media_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Media types'),
      '#description' => $this->t('Choose the media types that will be
        used to create new media entities based on matching extensions. Please be
        aware that if file extensions overlap between the media types that are
        chosen, that the media entity will be assigned automatically to one of
        these types.'),
      '#options' => $this->getMediaTypeOptions(),
      '#default_value' => $media_types ?? [],
      '#size' => 20,
      '#multiple' => TRUE,
      '#required' => TRUE,
    ];

    $form['form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form mode'),
      '#description' => $this->t('Based on the form mode the upload form
        can be enriched with fields that are available, improving the speed and
        usability to add (meta)data to your media entities.'),
      '#options' => $this->entityDisplayRepository->getFormModeOptions('media'),
      "#empty_option" => $this->t('- None -'),
      '#default_value' => $mediaBulkConfig->get('form_mode'),
    ];

    $form['upload_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Temporary upload location'),
      '#description' => $this->t('Temporary upload location of the files before they are moved to the determined
      location in the media types, for example temporary:// or private://upload_folder'),
      '#default_value' => $mediaBulkConfig->get('upload_location') ?: 'temporary://media-bulk-upload',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Get the available media type options.
   */
  private function getMediaTypeOptions() {
    $mediaTypeStorage = $this->entityTypeManager->getStorage('media_type');
    $mediaTypes = $mediaTypeStorage->loadMultiple();

    foreach ($mediaTypes as $mediaType) {
      $mediaTypeOptions[$mediaType->id()] = $mediaType->label();
    }
    natsort($mediaTypeOptions);

    return $mediaTypeOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $upload_location = $form_state->getValue('upload_location');
    $upload_location_ok = FALSE;
    $upload_location_error = NULL;

    // Check for file system and schema issues before using
    // FileSystemInterface::prepareDirectory() to create/check the directory.
    try {
      $scheme = $this->streamWrapperManager::getScheme($upload_location) ?? NULL;

      if (empty($upload_location)) {
        $upload_location_error = $this->t('No upload location provided.');
      }
      elseif ($scheme && str_starts_with($upload_location, 'private://') && !PrivateStream::basePath()) {
        $upload_location_error = $this->t('You need to configure and set up your private files directory before you can use it for Media Bulk Uploads.');
      }
      elseif ($scheme && str_starts_with($upload_location, 'temporary://')) {
        if (!$this->fileSystem->getTempDirectory()) {
          $upload_location_error = $this->t('You need to configure and set up your temporary files directory before you can use it for Media Bulk Uploads.');
        }
      }
      elseif (!$scheme ||
        !$this->streamWrapperManager->isValidScheme($scheme)) {
        $upload_location_error = $this->t('Invalid scheme provided. Standard schemes include public://, private:// or temporary://');
      }

      // Create the directory and give it the permissions needed.
      if (empty($upload_location_error)) {
        $upload_location_ok = $this->fileSystem->prepareDirectory(
          $upload_location,
          FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
        );
      }
    }
    catch (\Exception $e) {
      $form_state->setError(
        $form['upload_location'],
        $this->t(
          'Media upload location does not exist, or could not be created. (Error: %error)',
          ['%error' => $e->getMessage()]
          )
      );
    }

    // Check the result.
    if (!$upload_location_ok) {
      $form_state->setError(
        $form['upload_location'],
        $upload_location_error ?? $this->t('Media upload location does not exist, or could not be created.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $media_bulk_config = $this->entity;
    $status = $media_bulk_config->save();

    $save_message = $this->t('The configuration of %label has been saved.', [
      '%label' => $media_bulk_config->label(),
    ]);

    if ($status == SAVED_NEW) {
      $save_message = $this->t('The configuration of %label has been created.', [
        '%label' => $media_bulk_config->label(),
      ]);
    }

    $this->messenger->addMessage($save_message);
    $form_state->setRedirectUrl($media_bulk_config->toUrl('collection'));
    return $status == SAVED_NEW ? SAVED_NEW : SAVED_UPDATED;
  }

}
