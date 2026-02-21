<?php

declare(strict_types=1);

namespace Drupal\simplenews_stats\Plugin\views\field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Filter by actions.
 *
 * @ingroup simplenews_stats
 *
 * @ViewsField("simplenews_stats_entity_associated")
 */
class SimplenewsStatsEntityAssociated extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $newsletter = $values->_entity->getAssociatedEntity();

    if ($newsletter instanceof EntityInterface) {
      if (method_exists($newsletter, 'toLink') && $newsletter->hasLinkTemplate('canonical')) {
        return $newsletter->toLink()->toRenderable();
      }
      return $newsletter->label();
    }

    return $this->t('Deleted');
  }

}
