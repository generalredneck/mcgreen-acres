<?php

namespace Drupal\commerce_fee\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\entity\Form\EntityDuplicateFormTrait;

/**
 * Defines the fee add/edit form.
 */
class FeeForm extends ContentEntityForm {

  use EntityDuplicateFormTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Skip building the form if there are no available stores.
    $store_query = $this->entityTypeManager->getStorage('commerce_store')->getQuery();
    if ($store_query->count()->accessCheck()->execute() == 0) {
      $link = Link::createFromRoute('Add a new store.', 'entity.commerce_store.add_page');
      $form['warning'] = [
        '#markup' => t("Fees can't be created until a store has been added. @link", ['@link' => $link->toString()]),
      ];
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#tree'] = TRUE;
    // By default a fee type is preselected on the add form because the field
    // is required. Select an empty value instead, to force the user to choose.
    $user_input = $form_state->getUserInput();
    if ($this->operation == 'add' &&
      $this->entity->get('plugin')->isEmpty()) {
      if (!empty($form['plugin']['widget'][0]['target_plugin_id'])) {
        $form['plugin']['widget'][0]['target_plugin_id']['#empty_value'] = '';
        if (empty($user_input['plugin'][0]['target_plugin_id'])) {
          $form['plugin']['widget'][0]['target_plugin_id']['#default_value'] = '';
          unset($form['plugin']['widget'][0]['target_plugin_configuration']);
        }
      }
    }

    $translating = !$this->isDefaultFormLangcode($form_state);
    $hide_non_translatable_fields = $this->entity->isDefaultTranslationAffectedOnly();
    // The second column is empty when translating with non-translatable
    // fields hidden, so there's no reason to add it.
    if ($translating && $hide_non_translatable_fields) {
      return $form;
    }

    $form['#theme'] = ['commerce_fee_form'];
    $form['#attached']['library'][] = 'commerce_fee/form';

    $form['advanced'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-meta']],
      '#weight' => 99,
    ];
    $form['option_details'] = [
      '#type' => 'container',
      '#title' => $this->t('Options'),
      '#group' => 'advanced',
      '#attributes' => ['class' => ['entity-meta__header']],
      '#weight' => -100,
    ];
    $form['date_details'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Dates'),
      '#group' => 'advanced',
    ];

    $field_details_mapping = [
      'status' => 'option_details',
      'weight' => 'option_details',
      'order_types' => 'option_details',
      'stores' => 'option_details',
      'start_date' => 'date_details',
      'end_date' => 'date_details',
    ];
    foreach ($field_details_mapping as $field => $group) {
      if (isset($form[$field])) {
        $form[$field]['#group'] = $group;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $save = $this->entity->save();
    $this->postSave($this->entity, $this->operation);
    $this->messenger()->addMessage($this->t('Saved the %label fee.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.commerce_fee.collection');

    return $save;
  }

}
