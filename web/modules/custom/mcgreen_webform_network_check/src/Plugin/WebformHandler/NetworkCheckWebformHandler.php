<?php

namespace Drupal\mcgreen_webform_network_check\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\mcgreen_webform_network_check\NetworkClassifier;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Records which kind of network a submission came from.
 *
 * Writes "pass", "relay" or "hosting" into the webform's "network_check"
 * value element before the submission is saved. The handlers that email or
 * subscribe the submitted address carry a condition on that element, e.g.
 *
 * @code
 * conditions:
 *   enabled:
 *     ':input[name="network_check"]':
 *       '!value': hosting
 * @endcode
 *
 * so hosting-network submissions are saved (quarantined) and shown the normal
 * confirmation, but nothing is sent or subscribed. They are reported by the
 * cron digest and purged after a retention period.
 *
 * All of those handlers act in postSave(), which runs after every handler's
 * preSave(), so the element is always set before their conditions are
 * checked.
 *
 * @WebformHandler(
 *   id = "network_check",
 *   label = @Translation("Network check"),
 *   category = @Translation("Spam"),
 *   description = @Translation("Flags submissions from hosting / data-center networks so other handlers can skip them."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class NetworkCheckWebformHandler extends WebformHandlerBase {

  /**
   * The value element the classification is written to.
   */
  const ELEMENT = 'network_check';

  /**
   * The network classifier.
   *
   * @var \Drupal\mcgreen_webform_network_check\NetworkClassifier
   */
  protected $classifier;

  /**
   * The module's logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $networkLogger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->classifier = $container->get('mcgreen_webform_network_check.classifier');
    $instance->networkLogger = $container->get('logger.channel.mcgreen_webform_network_check');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#markup' => $this->hasElement()
        ? $this->t('Writes pass / relay / hosting to the %element element.', ['%element' => static::ELEMENT])
        : $this->t('Missing a %element value element: nothing is being checked.', ['%element' => static::ELEMENT]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['help'] = [
      '#type' => 'item',
      '#markup' => $this->t('Requires a <code>@element</code> element of type "value". Add a condition like <code>@condition</code> to every handler that emails or subscribes the submitted address. Networks are configured in <code>mcgreen_webform_network_check.settings</code>.', [
        '@element' => static::ELEMENT,
        '@condition' => ':input[name="' . static::ELEMENT . '"] !value hosting',
      ]),
    ];
    return $this->setSettingsParentsRecursively($form);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {
    if (!$this->hasElement()) {
      return;
    }
    // Classify once: on creation, or the first save that lacks a value
    // (e.g. a draft being completed). Never re-classify on staff edits.
    $current = $webform_submission->getElementData(static::ELEMENT);
    if (!$webform_submission->isNew() && $current !== NULL && $current !== '') {
      return;
    }

    $result = $this->classifier->classify((string) $webform_submission->getRemoteAddr());
    $webform_submission->setElementData(static::ELEMENT, $result['class']);

    if ($result['class'] !== NetworkClassifier::HOSTING) {
      return;
    }

    $network = NetworkClassifier::networkLabel($result);
    $note = sprintf('Network check: quarantined, %s (%s). Handlers that email or subscribe this address were skipped.', $network, $result['reason']);
    $webform_submission->setNotes(trim($webform_submission->getNotes() . "\n" . $note));

    $this->networkLogger->notice('Quarantined @webform submission from @ip, @network (@reason).', [
      '@webform' => $this->getWebform()->id(),
      '@ip' => $webform_submission->getRemoteAddr(),
      '@network' => $network,
      '@reason' => $result['reason'],
    ]);
  }

  /**
   * Whether the webform has the value element this handler writes to.
   */
  protected function hasElement(): bool {
    return (bool) $this->getWebform()->getElement(static::ELEMENT);
  }

}
