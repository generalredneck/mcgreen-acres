<?php

namespace Drupal\mcgreen_acres_store\Plugin\Field\FieldFormatter;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcgreen_acres_store\Form\OrderCoverFeesForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders field_cover_stripe_fees as an inline, editable form.
 *
 * Mirrors state_machine's "state_transition_form" formatter pattern, so the
 * fee toggle is editable directly from an order's canonical view page rather
 * than requiring a trip to the separate Edit tab.
 */
#[FieldFormatter(
  id: 'mcgreen_acres_store_cover_fees_form',
  label: new TranslatableMarkup('Editable toggle'),
  field_types: ['boolean'],
)]
class CoverFeesFormFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new object.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected ClassResolverInterface $classResolver,
    protected FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
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
      $container->get('class_resolver'),
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $items->getEntity();
    if (!$order->access('update')) {
      return [];
    }

    /** @var \Drupal\mcgreen_acres_store\Form\OrderCoverFeesForm $form_object */
    $form_object = $this->classResolver->getInstanceFromDefinition(OrderCoverFeesForm::class);
    $form_object->setEntity($order);

    $form_state = new FormState();
    // Boolean fields can't be multivalue, so it's safe to hardcode delta 0.
    return [0 => $this->formBuilder->buildForm($form_object, $form_state)];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getName() === 'field_cover_stripe_fees';
  }

}
