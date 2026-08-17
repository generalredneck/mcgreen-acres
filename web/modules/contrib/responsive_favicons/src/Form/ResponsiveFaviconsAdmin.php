<?php

namespace Drupal\responsive_favicons\Form;

use Drupal\Core\Archiver\ArchiverManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\Exception\FileWriteException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\responsive_favicons\ResponsiveFavicons;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Responsive Favicons settings form.
 *
 * @package Drupal\responsive_favicons\Form
 */
class ResponsiveFaviconsAdmin extends ConfigFormBase {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * Constructs a ResponsiveFaviconsAdmin object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator service.
   * @param \Drupal\Core\Archiver\ArchiverManager $archiverManager
   *   The archiver manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\responsive_favicons\ResponsiveFavicons $responsiveFavicons
   *   The module handler service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected FileSystemInterface $fileSystem,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected ArchiverManager $archiverManager,
    protected CacheBackendInterface $cache,
    protected ModuleHandlerInterface $moduleHandler,
    protected ResponsiveFavicons $responsiveFavicons,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('file_system'),
      $container->get('file_url_generator'),
      $container->get('plugin.manager.archiver'),
      $container->get('cache.default'),
      $container->get('module_handler'),
      $container->get('responsive_favicons.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'responsive_favicons_admin';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'responsive_favicons.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('responsive_favicons.settings');
    $form['path_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Favicons location'),
      '#description' => $this->t('Upload favicons using zip file from realfavicongenerator.net or provide path with location of the files.'),
      '#options' => [
        'upload' => $this->t('Upload zip file'),
        'path' => $this->t('Use internal path'),
      ],
      '#default_value' => $config->get('path_type'),
    ];
    $form['upload_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to responsive favicon files'),
      '#description' => $this->t('A local file system path where favicon files will be stored. This directory must exist and be writable by Drupal. An attempt will be made to create this directory if it does not already exist.'),
      '#field_prefix' => $this->fileUrlGenerator->generateAbsoluteString('public://'),
      '#default_value' => $config->get('path'),
      '#states' => [
        'visible' => [
          ':input[name="path_type"]' => ['value' => 'upload'],
        ],
        'required' => [
          ':input[name="path_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['local_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to responsive favicon files'),
      '#description' => $this->t('A local file system path where favicon files are stored (e.g. <code>/themes/custom/your-theme/favicons</code>). This directory must exist, relative to your Drupal root and contain all required files.'),
      '#default_value' => $config->get('path'),
      '#field_prefix' => DRUPAL_ROOT,
      '#states' => [
        'visible' => [
          ':input[name="path_type"]' => ['value' => 'path'],
        ],
      ],
    ];
    $form['tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Favicon tags'),
      '#description' => $this->t('Paste the code provided by <a href="http://realfavicongenerator.net/" target="_blank">http://realfavicongenerator.net/</a>. Make sure each link is on a separate line. It is fine to paste links with paths like <code>/apple-touch-icon-57x57.png</code> as these will be converted to the correct paths automatically.'),
      '#default_value' => implode(PHP_EOL, $config->get('tags')),
      '#rows' => 16,
    ];
    $form['upload'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload a zip file from realfavicongenerator.net to install'),
      '#description' => $this->t('For example: %filename from your local computer. This only needs to be done once.', ['%filename' => 'favicons.zip']),
      '#states' => [
        'visible' => [
          ':input[name="path_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['remove_existing_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove previously uploaded files'),
      '#description' => $this->t('If checked, all existing files will be recursively deleted from the upload folder.  Use with care.'),
      '#default_value' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="files[upload]"]' => ['filled' => TRUE],
        ],
      ],
    ];
    $form['cache_refresh_suffix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add a cache refresh suffix to icons URLs'),
      '#description' => $this->t("Allow updating icons without requiring a manual browser cache reset."),
      '#default_value' => $config->get('cache_refresh_suffix') ?? 0,
    ];
    $form['show_missing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show tags even if files are missing'),
      '#description' => $this->t('Allow displaying tags even if the referenced icon files are not available.'),
      '#default_value' => $config->get('show_missing') ?? 0,
    ];
    $form['remove_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove default favicon from Drupal'),
      '#description' => $this->t('It is recommended to remove default favicon as it can cause issues'),
      '#default_value' => $config->get('remove_default'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $path_type = $form_state->getValue('path_type');
    if ($path_type === 'path') {
      $path = rtrim($form_state->getValue('local_path'));
      if (!is_dir(DRUPAL_ROOT . $path)) {
        $form_state->setErrorByName('local_path', $this->t('The directory %dir does not exist.', ['%dir' => $path]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('responsive_favicons.settings');

    // We want to save tags as an array.
    $tags = explode(PHP_EOL, $form_state->getValue('tags'));
    $tags = array_map('trim', $tags);
    $tags = array_filter($tags);
    $config->set('tags', $tags);

    // Get the favicon location type.
    $path_type = $form_state->getValue('path_type');
    $config->set('path_type', $path_type);

    // Local path.
    if ($path_type === 'path') {
      $path = rtrim($form_state->getValue('local_path'));
      $config->set('path', $path);
    }

    // Checkboxes.
    $config->set('cache_refresh_suffix', $form_state->getValue('cache_refresh_suffix'));
    $config->set('show_missing', $form_state->getValue('show_missing'));
    $config->set('remove_default', $form_state->getValue('remove_default'));

    // If the path type is upload, handle the uploaded zip file.
    if ($path_type === 'upload') {
      $path = rtrim($form_state->getValue('upload_path'));
      $config->set('path', $path);

      // Attempt the upload and extraction of the zip file. This code is largely
      // based on the code in Drupal core.
      //
      // @see UpdateManagerInstall->submitForm().
      $local_cache = NULL;
      if (!empty($_FILES['files']['name']['upload'])) {
        $validators = ['FileExtension' => ['extensions' => 'zip']];
        if (!($finfo = file_save_upload('upload', $validators, NULL, 0, FileExists::Replace))) {
          // Failed to upload the file. file_save_upload() calls
          // \Drupal\Core\Messenger\MessengerInterface::addError() on failure.
          return;
        }
        $local_cache = $finfo->getFileUri();
      }

      // Only execute the below if a file was uploaded.
      if (isset($local_cache)) {
        $directory = $this->extractDirectory();
        try {
          $archive = $this->archiveExtract($local_cache, $directory);
        }
        catch (\Exception $e) {
          $this->messenger()->addError($e->getMessage());
          return;
        }

        $files = $archive->listContents();

        // Display a warning if the zip file is empty.
        if (!$files) {
          $this->messenger()->addError($this->t('The uploaded archive is empty.'));
          return;
        }

        $destination = 'public://' . $path;
        // Remove existing files if requested.
        if ($form_state->getValue('remove_existing_files')) {
          $this->fileSystem->deleteRecursive($destination);
        }
        // Recreated directory to extract the new files.
        $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);

        // Copy the files to the correct location.
        $success_count = 0;
        foreach ($files as $file) {
          // Handle exceptions when copy does not happen correctly.
          try {
            $success = $this->fileSystem->copy($directory . '/' . $file, $destination, FileExists::Replace);
          }
          catch (FileException $e) {
            $success = FALSE;
          }
          if ($success) {
            $uri = $destination . '/' . $file;
            $success_count++;
            // Handle exceptions when file contents are not saved correctly into
            // destination.
            try {
              // Rewrite the paths of the JSON files.
              if (preg_match('/\.json$/', $file)) {
                $file_contents = file_get_contents($uri);
                $find = preg_quote('"\/android-chrome', '/');
                $replace = '"' . str_replace('/', '\/', $this->responsiveFavicons->normalisePath('/android-chrome${1}', $config));
                $file_contents = preg_replace('/' . $find . '([^"]*)/', $replace, $file_contents);
                $this->fileSystem->saveData($file_contents, $uri, FileExists::Replace);
              }
              // Rewrite the paths of the XML files.
              elseif (preg_match('/\.xml$/', $file)) {
                $file_contents = file_get_contents($uri);
                $file_contents = $this->replacePath('/mstile', $file_contents, $config);
                $this->fileSystem->saveData($file_contents, $uri, FileExists::Replace);
              }
              // Rewrite the paths of the WEBMANIFEST files.
              elseif (preg_match('/\.webmanifest$/', $file)) {
                $file_contents = file_get_contents($uri);
                $file_contents = $this->replacePath('/android-chrome', $file_contents, $config);
                $file_contents = $this->replacePath('/web-app-manifest', $file_contents, $config);
                $this->fileSystem->saveData($file_contents, $uri, FileExists::Replace);
              }
            }
            catch (FileWriteException $e) {
              $this->messenger()->addError($this->t('The file could not be created.'));
            }
            catch (FileException $e) {
              $this->messenger()->addError($e->getMessage());
            }
          }
        }

        if ($success_count > 0) {
          $this->messenger()->addStatus($this->formatPlural($success_count, 'Uploaded 1 favicon file successfully.', 'Uploaded @count favicon files successfully.'));
        }
      }
    }

    // Save the settings.
    $config->save();
    parent::submitForm($form, $form_state);

    // Clear the icons' cache.
    // Needed until the following domain module issue gets fixed:
    // https://www.drupal.org/project/domain/issues/3397693
    $this->cache->delete($this->responsiveFavicons->getCacheId($config));

    // Check if all icons' files are available.
    $html = implode(PHP_EOL, $tags);
    $icons = $this->responsiveFavicons->validateTags($html, $config);
    if (!empty($icons['missing'])) {
      $this->messenger()->addWarning($this->t('The favicon files are missing for the following tags.<br/><code>@tags</code>', [
        ':url' => Url::fromRoute('responsive_favicons.admin')->toString(),
        '@tags' => implode(', ', $icons['missing']),
      ]));
    }

    // Display a warning if a manifest link is defined and the pwa module is
    // active.
    if ($this->moduleHandler->moduleExists('pwa') && $this->responsiveFavicons->hasLink($icons, 'rel', 'manifest')) {
      $this->messenger()->addWarning($this->t('The PWA module is active and a conflicting web manifest has been declared in the Favicon tags field. Please remove it.'));
    }
  }

  /**
   * Replace URLs starting with a specific prefix.
   *
   * @param string $prefix
   *   The prefix of the URLs to replace.
   * @param string $file_contents
   *   The content to search for replacements.
   * @param \Drupal\Core\Config\Config $config
   *   The module configuration.
   *
   * @return string
   *   The result content with the replaced URLs.
   */
  private function replacePath(string $prefix, string $file_contents, Config $config): string {
    return preg_replace_callback(
      '/"' . preg_quote($prefix, '/') . '([^"]*)/',
      function ($matches) use ($prefix, $config) {
        return '"' . $this->responsiveFavicons->normalisePath($prefix . $matches[1], $config);
      },
      $file_contents
    );
  }

  /**
   * Returns a short unique identifier for this Drupal installation.
   *
   * @return string
   *   An eight character string uniquely identifying this Drupal installation.
   */
  private function uniqueIdentifier() {
    $id = &drupal_static(__FUNCTION__, '');
    if (empty($id)) {
      $id = substr(hash('sha256', Settings::getHashSalt()), 0, 8);
    }
    return $id;
  }

  /**
   * Gets the directory where responsive favicons zip files should be extracted.
   *
   * @param bool $create
   *   (optional) Whether to attempt to create the directory if it does not
   *   already exist. Defaults to TRUE.
   *
   * @return string
   *   The full path to the temporary directory where responsive favicons fil
   *   archives should be extracted.
   */
  private function extractDirectory($create = TRUE) {
    $directory = &drupal_static(__FUNCTION__, '');
    if (empty($directory)) {
      $directory = 'temporary://responsive-favicons-' . $this->uniqueIdentifier();
      if ($create && !file_exists($directory)) {
        mkdir($directory);
      }
    }
    return $directory;
  }

  /**
   * Unpacks a downloaded archive file.
   *
   * @param string $file
   *   The filename of the archive you wish to extract.
   * @param string $directory
   *   The directory you wish to extract the archive into.
   *
   * @return \Drupal\Core\Archiver\ArchiverInterface
   *   The Archiver object used to extract the archive.
   *
   * @throws \Exception
   */
  private function archiveExtract($file, $directory) {
    $archiver = $this->archiverManager->getInstance(['filepath' => $file]);
    if (!$archiver) {
      throw new \Exception($this->t('Cannot extract %file, not a valid archive.', ['%file' => $file]));
    }

    if (file_exists($directory)) {
      $this->fileSystem->deleteRecursive($directory);
    }

    $archiver->extract($directory);
    return $archiver;
  }

}
