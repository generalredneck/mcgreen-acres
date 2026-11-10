<?php

namespace Drupal\media_bulk_upload;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a listing of Media Bulk Config entities.
 */
class MediaBulkConfigListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Media bulk upload configuration');
    $header['id'] = $this->t('Machine name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t(
      'There is no media bulk upload configuration yet. <a href=":url">Add a new media bulk upload configuration.</a>.',
      [
        ':url' => Url::fromRoute('entity.media_bulk_config.add_form')->toString(),
      ]);
    return $build;
  }

}
