<?php

namespace Drupal\drimage_s3fs\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\drimage_improved\Plugin\Field\FieldFormatter\DrImageFormatter;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\Error;
use Drupal\s3fs\S3fsException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\s3fs\S3fsServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin implementation of the 'dynamic responsive image' formatter.
 *
 * @FieldFormatter(
 *   id = "drimage_s3fs",
 *   label = @Translation("Drimage S3 Formatter"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class DrimageS3fsFormatter extends DrImageFormatter implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * S3fs service.
   *
   * @var \Drupal\s3fs\S3fsServiceInterface
   */
  protected $s3fs;

  /**
   * The module_handler service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a DrimageS3fsFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The image style storage.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\s3fs\S3fsServiceInterface $s3fs
   *   S3fs Service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config Factory service.
   *  @param \Psr\Log\LoggerInterface $logger
   *   Logger service.
   */
  public function __construct(
    $plugin_id, 
    $plugin_definition, 
    FieldDefinitionInterface $field_definition, 
    array $settings, $label, $view_mode, 
    array $third_party_settings, 
    AccountInterface $current_user, 
    EntityStorageInterface $image_style_storage, 
    FileUrlGeneratorInterface $file_url_generator, 
    Connection $database, 
    S3fsServiceInterface $s3fs, 
    ConfigFactoryInterface $configFactory, 
    LoggerInterface $logger
    ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $current_user, $image_style_storage, $file_url_generator);
    $this->database = $database;
    $this->s3fs = $s3fs;
    $this->configFactory = $configFactory;
    $this->logger = $logger;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('file_url_generator'),
      $container->get('database'),
      $container->get('s3fs'),
      $container->get('config.factory'),
      $container->get('logger.factory')->get('drimage_s3fs')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    // Get the s3 config to generate the host from the s3.
    $s3_config = $this->configFactory->get('s3fs.settings')->get();

    $host = "";
    if ($s3_config['use_cname'] && !empty($s3_config['domain'])) {
      $host = $s3_config['domain'];
    }
    else {
      try {
        $s3 = $this->s3fs->getAmazonS3Client($s3_config);
        $schema = $s3->getEndpoint()->getScheme();
        $host = $s3->getEndpoint()->getHost();
        $host = $schema . "://" . $host . '/' . $s3_config['bucket'];
      }
      catch (S3fsException $e) {
        $exception_variables = Error::decodeException($e);
        $this->logger->error('AmazonS3Client error: @message', $exception_variables);
      }
    }

    foreach ($elements as $delta => $element) {
      // Check if the element is not empty.
      if ($element) {
        $elements[$delta]['#theme'] = 'drimage_s3_formatter';
        $elements[$delta]['#item_attributes']['class'] = ['s3_drimage'];
        // Get the file name from the element data.
        $file_name = $element['#data']['filename']; 
        $elements[$delta]['#data']['subdir'] = 's3/files';
        // Query the database to check if the file exists in the s3fs_file table.
        $connection = $this->database;
        $query = $connection->select('s3fs_file', 's')
          ->fields('s', ['uri'])
          ->condition('uri', '%' . $connection->escapeLike($file_name) . '%', 'LIKE')
          ->condition('uri', '%' . 'drimage_improved' . '%', 'LIKE')
          ->execute();
        // Fetch all URIs that match the query.
        $file_exists = [];
        foreach ($query as $record) {
          $file_exists[] = $record->uri;
        }
        // Add the result to the element.
        $elements[$delta]['#data']['file_exists'] = $file_exists;

        // Add the host to the element.
        $elements[$delta]['#data']['s3_host'] = $host;
      }
    }
    return $elements;
  }

}
