<?php

namespace Drupal\paragraphs_edit\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\paragraphs_edit\ParagraphFormHelperTrait;

/**
 * Class ParagraphEditForm contains form for paragraph edit operation.
 */
class ParagraphEditForm extends ContentEntityForm {
  use ParagraphFormHelperTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#title'] = $this->t('Edit @lineage', [
      '@lineage' => $this->lineageInspector()->getLineageString($this->entity),
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($this->lineageRevisioner()->shouldCreateNewRevision($this->rootParent)) {
      return $this->lineageRevisioner()->saveNewRevision($this->entity);
    }
    else {
      return $this->entity->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);

    $destination = $this->getRequest()->query->get('destination');

    if ($destination) {
      $cancel_url = Url::fromUserInput($destination);
    }
    else {
      $cancel_url = $this->rootParent->toUrl();
    }

    $actions['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#attributes' => ['class' => ['button', 'dialog-cancel']],
      '#url' => $cancel_url,
    ];

    return $actions;
  }

}
