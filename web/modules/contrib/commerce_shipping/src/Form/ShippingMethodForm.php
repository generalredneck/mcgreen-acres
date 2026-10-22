<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\entity\Form\EntityDuplicateFormTrait;
use Drupal\field\Entity\FieldConfig;

class ShippingMethodForm extends ContentEntityForm {

  use EntityDuplicateFormTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Skip building the form if there are no available stores.
    $store_query = $this->entityTypeManager->getStorage('commerce_store')->getQuery()->accessCheck(FALSE);
    if ($store_query->count()->execute() == 0) {
      $link = Link::createFromRoute('Add a new store.', 'entity.commerce_store.add_page');
      $form['warning'] = [
        '#markup' => $this->t("Shipping methods can't be created until a store has been added. @link", ['@link' => $link->toString()]),
      ];
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = $this->entity->save();
    $this->messenger()->addMessage($this->t('Saved the %label shipping method.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.commerce_shipping_method.collection');

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function setFormDisplay(EntityFormDisplayInterface $form_display, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
    $shipping_method = $this->getEntity();
    $configured_fields = [];
    foreach ($shipping_method->getFieldDefinitions() as $field_definition) {
      if ($field_definition instanceof FieldConfig) {
        $configured_fields[] = $field_definition->getName();
      }
    }

    // When no fields were added via UI proceed as default.
    if (empty($configured_fields)) {
      return parent::setFormDisplay($form_display, $form_state);
    }

    // Move the fields added via the UI right after 'plugin', pushing the
    // fields that were already positioned after 'plugin' (e.g. 'conditions')
    // further down, past the newly added fields.
    $plugin_component = $form_display->getComponent('plugin');
    $plugin_weight = $plugin_component ? $plugin_component['weight'] : 1;

    // Collect every component currently weighted after 'plugin', so they
    // get pushed below the configured fields regardless of their name.
    $fields_after_plugin = array_filter($form_display->getComponents(), static function ($component) use ($plugin_weight) {
      return $component['weight'] > $plugin_weight;
    });
    usort($fields_after_plugin, static function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    // The configured fields are already part of $fields_after_plugin (they
    // were placed there by the default form display); exclude them here so
    // they aren't listed twice once merged back in below.
    $fields_after_plugin = array_diff(array_keys($fields_after_plugin), $configured_fields);

    // Re-weight sequentially: 'plugin', then the configured fields, then
    // everything else that used to follow 'plugin'.
    $weight = $plugin_weight + 1;
    $components = array_merge($configured_fields, $fields_after_plugin);
    foreach ($components as $field_name) {
      $component = $form_display->getComponent($field_name);
      if ($component) {
        $component['weight'] = $weight++;
        $form_display->setComponent($field_name, $component);
      }
    }

    return parent::setFormDisplay($form_display, $form_state);
  }

}
