<?php

declare(strict_types=1);

namespace Drupal\drimage_improved\Drush\Commands;

use Drupal\crop\Entity\CropType;
use Drupal\drimage_improved\ImageStyleRepositoryInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for the generated Drimage image styles.
 */
final class ImageStyleDeleteCommands extends DrushCommands {

  /**
   * Constructs the command.
   */
  public function __construct(private readonly ImageStyleRepositoryInterface $imageStyleRepository) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('drimage_improved.image_style_repository'));
  }

  /**
   * Deletes the generated Drimage image styles.
   */
  #[CLI\Command(name: 'drimage_improved:delete-styles', aliases: ['drimage_improved-delete-styles'])]
  #[CLI\Option(name: 'crop-type', description: 'Only delete the styles of this crop type.')]
  public function deleteStyles(array $options = ['crop-type' => self::OPT]): void {
    $count = $options['crop-type']
      ? $this->imageStyleRepository->deleteByCropType(CropType::load($options['crop-type']))
      : $this->imageStyleRepository->deleteAll();
    $this->logger()->success(dt('Deleted @count image styles.', ['@count' => $count]));
  }

}
