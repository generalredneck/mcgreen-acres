<?php

declare(strict_types=1);

namespace Drupal\diff\Form;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\diff\DiffEntityComparison;
use Drupal\diff\DiffLayoutManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for revision overview page.
 */
class RevisionOverviewForm extends FormBase {

  protected ImmutableConfig $config;

  /**
   * Constructs a RevisionOverviewForm object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
    protected DateFormatterInterface $date,
    protected RendererInterface $renderer,
    protected LanguageManagerInterface $languageManager,
    protected DiffLayoutManager $diffLayoutManager,
    protected DiffEntityComparison $entityComparison,
  ) {
    $this->config = $this->config('diff.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('renderer'),
      $container->get('language_manager'),
      $container->get('plugin.manager.diff.layout'),
      $container->get('diff.entity_comparison'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'revision_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL) {
    if (!$node instanceof NodeInterface) {
      return [];
    }

    $account = $this->currentUser;
    $langcode = $node->language()->getId();
    $langname = $node->language()->getName();
    $languages = $node->getTranslationLanguages();
    $has_translations = (\count($languages) > 1);

    $vids = $this->getRevisionIds($node);

    $revision_count = \count($vids);

    $build = [];
    if ($has_translations) {
      $build['#title'] = $this->t('@langname revisions for %title', [
        '@langname' => $langname,
        '%title' => $node->label(),
      ]);
    }
    else {
      $build['#title'] = $this->t('Revisions for %title', [
        '%title' => $node->label(),
      ]);
    }
    $build['nid'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
    ];

    $table_header = [];
    $table_header['revision'] = $this->t('Revision information');

    // Allow comparisons only if there are 2 or more revisions.
    $table_caption = '';
    if ($revision_count > 1) {
      $table_caption = $this->t('Use the radio buttons in the table below to select two revisions to compare. Then click the "Compare selected revisions" button to generate the comparison.');
      $table_header += [
        'select_column_one' => $this->t('Source revision'),
        'select_column_two' => $this->t('Target revision'),
      ];
    }
    $table_header['operations'] = $this->t('Operations');

    $type = $node->getType();
    $rev_revert_perm = $account->hasPermission("revert $type revisions") ||
      $account->hasPermission('revert all revisions') ||
      $account->hasPermission('administer nodes');
    $rev_delete_perm = $account->hasPermission("delete $type revisions") ||
      $account->hasPermission('delete all revisions') ||
      $account->hasPermission('administer nodes');
    $revert_permission = $rev_revert_perm && $node->access('update');
    $delete_permission = $rev_delete_perm && $node->access('delete');

    // Submit button for the form.
    $compare_revision_submit = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => \t('Compare selected revisions'),
      '#attributes' => [
        'class' => [
          'diff-button',
        ],
      ],
    ];

    // For more than 5 revisions, add a submit button on top of the screen.
    if ($revision_count > 5) {
      $build['submit_top'] = $compare_revision_submit;
    }

    // Contains the table listing the revisions.
    $build['node_revisions_table'] = [
      '#type' => 'table',
      '#caption' => $table_caption,
      '#header' => $table_header,
      '#attributes' => ['class' => ['diff-revisions']],
    ];

    $build['node_revisions_table']['#attached']['library'][] = 'diff/diff.general';
    $build['node_revisions_table']['#attached']['drupalSettings']['diffRevisionRadios'] = $this->config->get('general_settings.radio_behavior');

    $default_revision = $node->getRevisionId();
    $node_storage = $this->entityTypeManager->getStorage('node');
    $current_revision_displayed = FALSE;
    /** @var \Drupal\node\NodeInterface[] $revisions */
    $revisions = $node_storage->loadMultipleRevisions($vids);
    // Add rows to the table.
    foreach ($vids as $key => $vid) {
      $revision = $revisions[$vid] ?? NULL;
      if (!$revision instanceof NodeInterface) {
        continue;
      }

      $previous_revision = isset($vids[$key + 1]) ? $revisions[$vids[$key + 1]] ?? NULL : NULL;

      $username = [
        '#theme' => 'username',
        '#account' => $revision->getRevisionUser(),
      ];
      $revision_date = $this->date->format($revision->getRevisionCreationTime(), 'short');

      // We treat also the latest translation-affecting revision as current
      // revision, if it was the default revision, as its values for the
      // current language will be the same of the current default revision in
      // this case.
      $is_current_revision = $revision->isDefaultRevision() || (!$current_revision_displayed && $revision->wasDefaultRevision());
      if ($is_current_revision) {
        $link = $node->toLink($revision_date);
        $current_revision_displayed = TRUE;
      }
      else {
        $link = Link::fromTextAndUrl($revision_date, new Url('entity.node.revision', ['node' => $node->id(), 'node_revision' => $vid]));
      }

      if ($vid == $default_revision) {
        $row = [
          'revision' => $this->buildRevision($link, $username, $revision, $previous_revision),
        ];

        // Allow comparisons only if there are 2 or more revisions.
        if ($revision_count > 1) {
          $row += [
            'select_column_one' => $this->buildSelectColumn('radios_left', $vid, FALSE),
            'select_column_two' => $this->buildSelectColumn('radios_right', $vid, $vid),
          ];
        }
        $row['operations'] = [
          '#prefix' => '<em>',
          '#markup' => $this->t('Current revision'),
          '#suffix' => '</em>',
          '#attributes' => [
            'class' => ['revision-current'],
          ],
        ];
        $row['#attributes'] = [
          'class' => ['revision-current'],
        ];
      }
      else {
        $route_params = [
          'node' => $node->id(),
          'node_revision' => $vid,
          'langcode' => $langcode,
        ];
        $links = [];
        if ($revert_permission) {
          $links['revert'] = [
            'title' => $vid < $node->getRevisionId() ? $this->t('Revert') : $this->t('Set as current revision'),
            'url' => $has_translations ?
            Url::fromRoute('node.revision_revert_translation_confirm', ['node' => $node->id(), 'node_revision' => $vid, 'langcode' => $langcode]) :
            Url::fromRoute('node.revision_revert_confirm', ['node' => $node->id(), 'node_revision' => $vid]),
          ];
        }
        if ($delete_permission) {
          $links['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('node.revision_delete_confirm', $route_params),
          ];
        }

        // Here we don't have to deal with 'only one revision' case because
        // if there's only one revision it will also be the default one,
        // entering on the first branch of this if else statement.
        $row = [
          'revision' => $this->buildRevision($link, $username, $revision, $previous_revision),
          'select_column_one' => $this->buildSelectColumn('radios_left', $vid, $vids[1] ?? FALSE),
          'select_column_two' => $this->buildSelectColumn('radios_right', $vid, FALSE),
          'operations' => [
            '#type' => 'operations',
            '#links' => $links,
          ],
        ];
      }
      // Add the row to the table.
      $build['node_revisions_table'][] = $row;
    }

    // Allow comparisons only if there are 2 or more revisions.
    if ($revision_count > 1) {
      $build['submit'] = $compare_revision_submit;
    }
    $build['pager'] = [
      '#type' => 'pager',
    ];
    $build['#attached']['library'][] = 'node/drupal.node.admin';
    $form_state->set('workspace_safe', TRUE);
    return $build;
  }

  /**
   * Gets a list of node revision IDs for a specific node.
   *
   * Only returns revisions that are affected by the $node language.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return int[]
   *   Node revision IDs (in descending order).
   */
  protected function getRevisionIds(NodeInterface $node): array {
    $entityType = $node->getEntityType();
    $result = $this->entityTypeManager->getStorage('node')->getQuery()
      // Access to the content has already been verified. Disable query-level
      // access checking so that revisions for unpublished content still
      // appear.
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($entityType->getKey('langcode'), $node->language()->getId())
      ->condition($entityType->getKey('revision_translation_affected'), '1')
      ->condition($entityType->getKey('id'), $node->id())
      ->sort($entityType->getKey('revision'), 'DESC')
      ->pager($this->config->get('general_settings.revision_pager_limit'))
      ->execute();
    return \array_keys($result);
  }

  /**
   * Set column attributes and return config array.
   *
   * @param string $name
   *   Name attribute.
   * @param int $return_val
   *   Return value attribute.
   * @param int|false $default_val
   *   Default value attribute.
   *
   * @return array
   *   Configuration array.
   */
  protected function buildSelectColumn($name, $return_val, $default_val): array {
    return [
      '#type' => 'radio',
      '#title_display' => 'invisible',
      '#name' => $name,
      '#return_value' => $return_val,
      '#default_value' => $default_val,
    ];
  }

  /**
   * Set and return configuration for revision.
   *
   * @param \Drupal\Core\Link $link
   *   Link attribute.
   * @param array $username
   *   Username render array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   Revision parameter for getRevisionDescription function.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $previous_revision
   *   (optional) Previous revision for getRevisionDescription function.
   *   Defaults to NULL.
   *
   * @return array
   *   Configuration for revision.
   */
  protected function buildRevision(Link $link, $username, ContentEntityInterface $revision, ?ContentEntityInterface $previous_revision = NULL): array {
    return [
      '#type' => 'inline_template',
      '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
      '#context' => [
        'date' => $link->toString(),
        // @see https://www.drupal.org/node/3407994
        'username' => DeprecationHelper::backwardsCompatibleCall(
          currentVersion: \Drupal::VERSION,
          deprecatedVersion: '10.3',
          currentCallable: fn() => $this->renderer->renderInIsolation($username),
          deprecatedCallable: fn() => $this->renderer->renderPlain($username),
        ),
        'message' => [
          '#markup' => $this->entityComparison->getRevisionDescription($revision, $previous_revision),
          '#allowed_tags' => Xss::getAdminTagList(),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();

    // @todo The first 2 error messages should not be possible to hit now that
    // revisions are correctly displayed. Consider removing the validation.
    $revisions = $form_state->getValue('node_revisions_table');
    if (!\is_countable($revisions) || \count($revisions) <= 1) {
      $form_state->setErrorByName('node_revisions_table', $this->t('Multiple revisions are needed for comparison.'));
    }
    elseif (!isset($input['radios_left']) || !isset($input['radios_right'])) {
      $form_state->setErrorByName('node_revisions_table', $this->t('Select two revisions to compare.'));
    }
    elseif ($input['radios_left'] == $input['radios_right']) {
      // @todo Radio-boxes selection resets if there are errors.
      $form_state->setErrorByName('node_revisions_table', $this->t('Select different revisions to compare.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $vid_left = $input['radios_left'];
    $vid_right = $input['radios_right'];
    $nid = $input['nid'];

    // Always place the older revision on the left side of the comparison
    // and the newer revision on the right side (however revisions can be
    // compared both ways if we manually change the order of the parameters).
    if ($vid_left > $vid_right) {
      $aux = $vid_left;
      $vid_left = $vid_right;
      $vid_right = $aux;
    }
    // Builds the redirect Url.
    $redirect_url = Url::fromRoute(
      'diff.revisions_diff',
      [
        'node' => $nid,
        'left_revision' => $vid_left,
        'right_revision' => $vid_right,
        'filter' => $this->diffLayoutManager->getDefaultLayout(),
      ],
    );
    $form_state->setRedirectUrl($redirect_url);
  }

}
