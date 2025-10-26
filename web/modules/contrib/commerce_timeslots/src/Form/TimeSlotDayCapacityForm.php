<?php

namespace Drupal\commerce_timeslots\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Implementation of TimeSlotDayCapacityForm edit and create forms.
 */
class TimeSlotDayCapacityForm extends ContentEntityForm {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a BookOutlineForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    TimeInterface $time = NULL,
    DateFormatterInterface $date_formatter
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['warning'] = [
      '#markup' => $this->t('The time slot day capacity form.'),
      '#weight' => -100,
    ];
    $preprocess = '::preprocessDateField';
    $form['interval']['widget'][0]['value']['#after_build'][] = $preprocess;
    $form['interval']['widget'][0]['end_value']['#after_build'][] = $preprocess;

    // Display author information.
    $form['author'] = [
      '#markup' => $this->t('<strong>Author:</strong> <em>@author</em>', [
        '@author' => $this->entity->getOwner()->label(),
      ]),
    ];
    return $form;
  }

  /**
   * Preprocess the date field.
   *
   * @param array $element
   *   The form element.
   *
   * @return array
   *   Return the modified form element.
   */
  public function preprocessDateField(array $element): array {
    $current = $this->time->getCurrentTime();
    $timezone = 'UTC';
    $date = $this
      ->dateFormatter
      ->format($current, 'custom', $element['#date_date_format'], $timezone);

    if (!empty($element['#value']['date'])) {
      $existing_date = DrupalDateTime::createFromTimestamp(
        strtotime($element['#value']['date'] . 'T' . $element['#value']['time']),
        $timezone
      );
      $element['#value']['object'] = $existing_date;
    }

    if (!empty($element['time']['#value'])) {
      $time = $this
        ->dateFormatter
        ->format(
          strtotime($element['time']['#value']),
          'custom',
          $element['#date_time_format'],
          $timezone
        );

      $element['#value']['time'] = $time;
      $element['time']['#value'] = $time;
    }

    $element['time']['#attributes']['step'] = 60;
    $element['date']['#value'] = $date;
    $element['date']['#title_display'] = 'hidden';
    $element['date']['#attributes']['class'][] = 'hidden';
    $element['#date_timezone'] = $timezone;

    if (!empty($element['#default_value'])) {
      $element['#default_value']->setTimezone(new \DateTimeZone($timezone));
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $form_errors = $form_state->getErrors();

    // Reset form errors in case it's related to time range interval validation.
    if (in_array('interval][0', array_keys($form_errors))) {
      $form_state->clearErrors();
    }

    $interval = $form_state->getValue('interval')[0];
    $start = $interval['value']->getTimestamp();
    $end = $interval['end_value']->getTimestamp();

    if ($end < $start) {
      $form_state->setErrorByName(
        'interval',
        $this->t('The end time must be greater than start')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this
      ->messenger()
      ->addMessage($this->t(
        'The time slot day capacity %label have been saved.',
        ['%label' => $this->entity->label()]
      ));
    $form_state->setRedirect('entity.commerce_timeslot_day_capacity.collection');
  }

}
