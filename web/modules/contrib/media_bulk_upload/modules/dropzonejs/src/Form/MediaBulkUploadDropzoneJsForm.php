<?php

namespace Drupal\media_bulk_upload_dropzonejs\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\media_bulk_upload\Entity\MediaBulkConfigInterface;
use Drupal\media_bulk_upload\Form\MediaBulkUploadForm;

/**
 * Bulk media upload form using DropzoneJS.
 *
 * @package Drupal\media_upload\Form
 */
class MediaBulkUploadDropzoneJsForm extends MediaBulkUploadForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'media_bulk_upload_dropzone_js_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?MediaBulkConfigInterface $media_bulk_config = NULL): array {
    $form = parent::buildForm($form, $form_state, $media_bulk_config);
    $form['file_upload']['#type'] = 'dropzonejs';
    $form['file_upload']['#disable_form_buttons'] = '.button.form-submit';
    $form['file_upload']['#dropzone_description'] = $this->t('Click or drop your files here');

    if (isset($form['file_upload']['#upload_validators']['FileExtension']['extensions'])) {
      $form['file_upload']['#extensions'] = $form['file_upload']['#upload_validators']['FileExtension']['extensions'];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Validate all uploaded files.
    $uploaded_files = $form_state->getValue(['file_upload', 'uploaded_files']);
    if (empty($uploaded_files)) {
      $form_state->setErrorByName('file_upload', $this->t('No media files have been provided.'));
      return;
    }

    foreach ($uploaded_files as $uploaded_file) {
      // Create a new file entity since some modules only validate new files.
      $file = $this->fileStorage->create([
        'uri' => $uploaded_file['path'],
      ]);

      // Let other modules perform validation on the new file.
      $errors = $this->moduleHandler->invokeAll('file_validate', [$file]);

      // Process any reported errors.
      if (!empty($errors)) {
        $form_state->setErrorByName('file_upload', 'Errors for file ' . $file->getFilename() . ': ' . implode(', ', $errors));

        try {
          // Delete the uploaded file if it has validation errors.
          $this->fileSystem->delete($uploaded_file['path']);
        }
        catch (\Exception $e) {
          $this->loggerFactory->get('media_bulk_upload')->error($e);
        }
      }
    }
  }

}
