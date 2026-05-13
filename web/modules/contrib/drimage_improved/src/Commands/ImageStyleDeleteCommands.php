<?php

namespace Drupal\drimage_improved\Commands;

use Drupal\crop\Entity\CropType;
use Drupal\drimage_improved\ImageStyleRepositoryInterface;
use Drush\Commands\DrushCommands;

/**
 * Provides a Drush comand for deleting all generated drimage_improved image styles.
 */
class ImageStyleDeleteCommands extends DrushCommands {

  /**
   * The image style repository.
   *
   * @var \Drupal\drimage_improved\ImageStyleRepositoryInterface
   */
  protected $imageStyleRepository;

  /**
   * Constructs a new ImageStyleDeleteCommands object.
   *
   * @param \Drupal\drimage_improved\ImageStyleRepositoryInterface $imageStyleRepository
   *   The image style repository.
   */
  public function __construct(ImageStyleRepositoryInterface $imageStyleRepository) {
    parent::__construct();
    $this->imageStyleRepository = $imageStyleRepository;
  }

  /**
   * Delete all generated drimage_improved image styles.
   *
   * @command drimage_improved:delete-styles
   * @aliases drimage_improved-delete-styles
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
