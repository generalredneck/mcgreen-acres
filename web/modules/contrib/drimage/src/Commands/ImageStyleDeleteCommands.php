<?php

namespace Drupal\drimage\Commands;

use Drupal\crop\Entity\CropType;
use Drupal\drimage\ImageStyleRepositoryInterface;
use Drush\Commands\DrushCommands;

/**
 * Provides a Drush comand for deleting all generated drimage image styles.
 */
class ImageStyleDeleteCommands extends DrushCommands {

  /**
   * The image style repository.
   *
   * @var \Drupal\drimage\ImageStyleRepositoryInterface
   */
  protected $imageStyleRepository;

  /**
   * Constructs a new ImageStyleDeleteCommands object.
   *
   * @param \Drupal\drimage\ImageStyleRepositoryInterface $imageStyleRepository
   *   The image style repository.
   */
  public function __construct(ImageStyleRepositoryInterface $imageStyleRepository) {
    parent::__construct();
    $this->imageStyleRepository = $imageStyleRepository;
  }

  /**
   * Delete all generated drimage image styles.
   *
   * @command drimage:delete-styles
   * @aliases drimage-delete-styles
   *
   * @option crop-type
   *   An optional crop type to delete styles for.
   */
  public function deleteStyles(array $options = ['crop-type' => self::OPT]): void {
    if ($options['crop-type']) {
      $cropType = CropType::load($options['crop-type']);
      $count = $this->imageStyleRepository->deleteByCropType($cropType);
    }
    else {
      $count = $this->imageStyleRepository->deleteAll();
    }

    $this->logger()->success(dt('Deleted @count image styles.', ['@count' => $count]));
  }

}
