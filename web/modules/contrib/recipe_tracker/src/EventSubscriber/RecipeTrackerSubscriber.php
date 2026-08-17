<?php

declare(strict_types=1);

namespace Drupal\recipe_tracker\EventSubscriber;

use Composer\InstalledVersions;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\recipe_tracker\Entity\Log;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 *
 */
final class RecipeTrackerSubscriber implements EventSubscriberInterface {

  /**
   * The app root.
   *
   * @var string
   */
  private string $appRoot;

  /**
   * The event subscriber constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   */
  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    $appRoot,
  ) {
    $this->appRoot = $appRoot;
  }

  /**
   * Recipe was applied.
   */
  public function recipeApplied(RecipeAppliedEvent $event): void {
    $recipe = $event->recipe;
    $log = Log::create();
    $log->setOwnerId($this->currentUser->id());
    $log->setRecipeName($recipe->name);

    $package_name = NULL;
    if (str_starts_with($recipe->path, $this->appRoot . '/core/recipes/')) {
      $package_name = 'drupal/core';
    }
    else {
      // Get fully qualified name from composer.json file.
      try {
        $package = file_get_contents($recipe->path . '/composer.json');
        assert(is_string($package));
        $package = Json::decode($package);
        assert(isset($package['name']) && is_string($package['name']));
        $package_name = $package['name'];
      }
      catch (\Throwable $e) {
      }
    }

    if ($package_name) {
      $log->setPackageName($package_name);
      try {
        $version = InstalledVersions::getPrettyVersion($package_name);
        $log->setVersion($version);
      }
      catch (\Throwable $e) {
        $log->setVersion('@dev');
      }
    }

    $log->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RecipeAppliedEvent::class => ['recipeApplied'],
    ];
  }

}
