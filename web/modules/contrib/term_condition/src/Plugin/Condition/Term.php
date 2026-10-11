<?php

namespace Drupal\term_condition\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Term' condition plugin.
 *
 * Controls block visibility rules based on a taxonomy term referenced by the
 * node.
 *
 * @Condition(
 *   id = "term",
 *   label = @Translation("Term"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = FALSE , label = @Translation("node"))
 *   }
 * )
 */
class Term extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Term config key.
   *
   * @var string
   */
  protected const TERM_KEY = 'term_uuids';

  /**
   * Require all terms config key.
   *
   * @var string
   */
  protected const REQUIRE_ALL_KEY = 'require_all_terms';

  /**
   * Creates a new Term condition instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current_route_match service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, array $configuration, $plugin_id, $plugin_definition, protected RouteMatchInterface $routeMatch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $default_terms = $this->loadTerms();

    $form['terms'] = [
      '#type' => 'term_condition_entity_autocomplete',
      '#title' => $this->t('Select taxonomy term(s)'),
      '#default_value' => $default_terms,
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#maxlength' => 1024,
    ];

    $form['require_all_terms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require all terms'),
      '#description' => $this->t('When enabled, the condition only matches when the entity references <em>all</em> of the selected terms. When disabled, matching any one of the selected terms is sufficient.'),
      '#default_value' => $this->configuration[static::REQUIRE_ALL_KEY] ?? FALSE,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      static::TERM_KEY => [],
      static::REQUIRE_ALL_KEY => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $terms = $form_state->getValue('terms') ?? [];
    $uuids = [];

    // Load uuids for any selected terms.
    foreach ($terms as $term) {
      $uuids[] = $this->entityTypeManager->getStorage('taxonomy_term')->load($term['target_id'])->uuid();
    }
    $this->configuration[static::TERM_KEY] = $uuids;
    $this->configuration[static::REQUIRE_ALL_KEY] = (bool) $form_state->getValue('require_all_terms');

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    if (empty($this->configuration[static::TERM_KEY]) && !$this->isNegated()) {
      return TRUE;
    }

    $entity = $this->getContextValue('node');

    // Not in a node context. Try a few other options.
    if (!$entity) {

      // Potential other ways to try fetch the entity. Assoc array to try get
      // revisions. I wonder if there is a cleaner way to do this?
      // @todo Provide hook to add extras.
      $potential_route_matches = [
        'taxonomy_term' => 'taxonomy_term',
        'node' => 'node',
        'node_revision' => 'node_revision',
        'node_preview' => 'node_preview',
        'media' => 'media',
      ];
      foreach ($potential_route_matches as $key => $potential_route_match) {
        $entity = $this->routeMatch->getParameter($potential_route_match);
        // If the entity extends EntityInterface, we have the entity we want.
        if ($entity instanceof EntityInterface) {
          break;
        }
        elseif (is_string($entity)) {
          // If the entity is a string, its likely the revision ID,
          // try load that.
          $entity = $this->entityTypeManager->getStorage($key)->loadRevision($entity);
          break;
        }
      }
      // All checks failed. Stop.
      if (!$entity) {
        return FALSE;
      }
    }

    $referenced = [];
    foreach ($entity->referencedEntities() as $referenced_term) {
      // Fast-forward to next entity if this one is not taxonomy term.
      if ($referenced_term->getEntityTypeId() !== 'taxonomy_term') {
        continue;
      }

      $referenced[$referenced_term->uuid()] = TRUE;
    }

    $configured = $this->configuration[static::TERM_KEY];
    if (!empty($this->configuration[static::REQUIRE_ALL_KEY])) {
      foreach ($configured as $uuid) {
        if (!isset($referenced[$uuid])) {
          return FALSE;
        }
      }

      return TRUE;
    }

    foreach ($configured as $uuid) {
      if (isset($referenced[$uuid])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $terms = $this->loadTerms();
    $term_names = [];
    foreach ($terms as $term) {
      $term_names[] = $term->getName();
    }

    return $this->t('The node is @not associated with @match of the taxonomy term(s): @term_list.', [
      '@term_list' => implode(", ", $term_names),
      '@not' => $this->isNegated() ? 'not' : '',
      '@match' => !empty($this->configuration[static::REQUIRE_ALL_KEY]) ? 'all' : 'any',
    ]);
  }

  /**
   * Load terms referenced in configuration.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Terms from configuration.
   */
  protected function loadTerms(): array {
    $terms = [];

    if (!empty($this->configuration[static::TERM_KEY])) {
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['uuid' => $this->configuration[static::TERM_KEY]]);
    }

    return $terms;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    foreach ($this->loadTerms() as $term) {
      $dependencies['content'][] = implode(':', [$term->getEntityTypeId(), $term->bundle(), $term->uuid()]);
    }

    return $dependencies;
  }

}
