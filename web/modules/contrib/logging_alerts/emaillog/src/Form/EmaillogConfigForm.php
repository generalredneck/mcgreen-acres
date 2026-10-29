<?php

namespace Drupal\emaillog\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a setting UI for emaillog.
 */
class EmaillogConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'email_log_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames(): array {
    return ['emaillog.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Request $request = NULL): array {
    $severity_levels = RfcLogLevel::getLevels();

    $form['emaillog'] = [
      '#type' => 'details',
      '#title' => $this->t('Email addresses for each severity level'),
      '#description' => $this->t('Enter an email address for each severity level. For example, you may want to get emergency and critical levels to your pager or mobile phone, while notice level messages can go to a regular email. If you leave the email address blank for a severity level, no email messages will be sent for that severity level.'),
      '#open' => TRUE,
    ];
    $form['channels'] = [
      '#type' => 'details',
      '#title' => $this->t('Channel filters'),
      '#description' => $this->t('Enter a comma separated list of the channels to send emails for (for example <em>php, page not found, access denied</em>). Leave it empty to send log entries for all channels.'),
    ];
    foreach ($severity_levels as $severity => $level) {
      $key = 'emaillog_' . $severity;
      $form['emaillog'][$key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Email address for severity %description', ['%description' => Unicode::ucfirst($level->render())]),
        '#default_value' => $this->config('emaillog.settings')->get($key),
        '#description' => $this->t('The email address to send log entries of severity %description to.', ['%description' => Unicode::ucfirst($level->render())]),
      ];

      $key = 'emaillog_' . $severity . '_channels';
      $form['channels'][$key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Channels for severity %description', ['%description' => Unicode::ucfirst($level->render())]),
        '#default_value' => $this->config('emaillog.settings')->get($key),
        '#description' => $this->t('Comma separated list of channels to send log entries of severity %description for. Preface list with "!" to indicate that these channels are to be excluded instead.', ['%description' => Unicode::ucfirst($level->render())]),
      ];
    }

    $form['debug_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Additional debug info'),
      '#description' => $this->t('Select the information to add to email alerts. Server, request, cookie, session, and environment values can contain passwords or personal data. Other modules can change this information with <em>hook_emaillog_debug_info_alter(&$debug_info)</em>.'),
      '#tree' => TRUE,
    ];
    $debug_info_settings = $this->config('emaillog.settings')->get('emaillog_debug_info');
    $status = [];

    $form['debug_info']['variables'] = [
      '#type' => 'table',
      '#header' => array_merge(['empty' => ""], $severity_levels),
      '#attributes' => [
        'id' => 'permissions',
      ],
    ];

    foreach (_emaillog_get_debug_info_callbacks() as $debug_info_key => $debug_info_callback) {
      $row = NULL;

      // Permission row.
      $row['first'] = [
        '#type' => 'item',
        '#markup' => $debug_info_callback,
        '#attributes' => [
          'class' => ['variable'],
        ],
      ];
      foreach ($severity_levels as $level_id => $description) {
        $row[$level_id] = [
          '#title' => "",
          '#type' => 'checkbox',
          '#attributes' => [
            'class' => ['variable'],
          ],
          '#default_value' => $debug_info_settings[$level_id][$debug_info_key] ?? FALSE,
        ];
      }
      $form['debug_info']['variables'][$debug_info_key] = $row;
    }

    $form['debug_info']['emaillog_backtrace_replace_args'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show backtrace argument types and sizes'),
      '#description' => $this->t('Select this option to show each argument type and size. Clear it to show full argument values. Full values can contain passwords or personal data. Large values can use too much memory.'),
      '#default_value' => $this->config('emaillog.settings')->get('emaillog_backtrace_replace_args'),
      '#weight' => 1,
    ];
    $form['limits'] = [
      '#type' => 'details',
      '#title' => $this->t('Email sending limits'),
    ];
    $form['limits']['emaillog_max_similar_emails'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum number of allowed consecutive similar email alerts'),
      '#description' => $this->t('Upper limit of email alerts sent consecutively with the same or very similar message. Leave empty for no limit.'),
      '#default_value' => $this->config('emaillog.settings')->get('emaillog_max_similar_emails'),
    ];
    $form['limits']['emaillog_max_consecutive_timespan'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email alerts should be considered "consecutive" if sent within'),
      '#field_suffix' => $this->t('minutes from each other'),
      '#description' => $this->t('Longest possible period between two email alerts being sent to still be considered consecutive. Leave empty for no limit.'),
      '#default_value' => $this->config('emaillog.settings')->get('emaillog_max_consecutive_timespan'),
    ];
    $form['limits']['emaillog_max_similarity_level'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum allowed similarity level between consecutive email alerts'),
      '#description' => '<p>' . $this->t('Highest similarity level above which new email alerts will not be sent anymore if "Maximum number of allowed consecutive similar email alerts" has been reached and email alerts are considered "consecutive" (time period between each previous and next one is smaller than defined above). Possible values range from 0 to 1, where 1 stands for two identical emails.') . '</p>'
        . '<p>' . $this->t('For example setting "Maximum number of allowed consecutive similar email alerts" to 5, "Email alerts should be considered consecutive if sent within" to 5 minutes and "Similarity level" to 0.9 would mean that only 5 email alerts would be sent within 5 minutes if Watchdog entries are similar in at least 90%.') . '</p>'
        . '<p>' . $this->t("(Note that similarity level is calculated using PHP's <a href='@similar_text_url'>similar_text()</a> function, with all its complexity and implications.)", ['@similar_text_url' => Url::fromUri('http://php.net/similar_text')->getUri()]) . '</p>',
      '#default_value' => $this->config('emaillog.settings')->get('emaillog_max_similarity_level'),
    ];

    $form['subject'] = [
      '#type' => 'details',
      '#title' => $this->t('Email subject'),
    ];

    $form['subject']['emaillog_custom_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email subject'),
      '#description' => $this->t('The subject of the email alerts. You can use these placeholders: %site_name for the site name, %severity_desc for the severity level and %dblog_message for the first 60 characters of the log message. Leave it empty to use the default subject "%default".', [
        '%site_name' => '@site_name',
        '%severity_desc' => '@severity_desc',
        '%dblog_message' => '@dblog_message',
        '%default' => '[@site_name] @severity_desc: @dblog_message',
      ]),
      '#default_value' => $this->config('emaillog.settings')->get('emaillog_custom_subject'),
      '#states' => [
        'visible' => [
          ':input[name="emaillog_legacy_subject"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['subject']['emaillog_legacy_subject'] = [
      '#type'  => 'checkbox',
      '#title' => $this->t('Use legacy email subject'),
      '#description' => $this->t('Older versions of this module used the email subject "%subject". Select this option to use that subject instead of the email subject above.', [
        '%subject' => $this->t('[@site_name] @severity_desc: Alert from your web site'),
      ]),
      '#default_value' => $this->config('emaillog.settings')->get('emaillog_legacy_subject'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Configuration'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $userInputValues = $form_state->getUserInput();

    if ($userInputValues['emaillog_max_similar_emails'] && !$userInputValues['emaillog_max_similarity_level']) {
      $form_state->setErrorByName('emaillog_max_similarity_level', $this->t('You need to provide value for %field1 field when specifying %field2.', [
        '%field1' => 'Maximum allowed similarity level between consecutive email alerts',
        '%field2' => 'Maximum number of allowed consecutive similar emails',
      ]));
    }
    if ($userInputValues['emaillog_max_similarity_level'] && !$userInputValues['emaillog_max_similar_emails']) {
      $form_state->setErrorByName('emaillog_max_similar_emails', $this->t('You need to provide value for %field1 field when specifying %field2.', [
        '%field1' => 'Maximum number of allowed consecutive similar emails',
        '%field2' => 'Maximum allowed similarity level between consecutive email alerts',
      ]));
    }
    if ($userInputValues['emaillog_max_consecutive_timespan'] && !$userInputValues['emaillog_max_similar_emails']) {
      $form_state->setErrorByName('emaillog_max_similar_emails', $this->t('You need to provide value for %field1 field when specifying %field2.', [
        '%field1' => 'Maximum number of allowed consecutive similar emails',
        '%field2' => 'Email alerts should be considered "consecutive" if sent within',
      ]));
    }
    if ($userInputValues['emaillog_max_consecutive_timespan'] && !$userInputValues['emaillog_max_similarity_level']) {
      $form_state->setErrorByName('emaillog_max_similarity_level', $this->t('You need to provide value for %field1 field when specifying %field2.', [
        '%field1' => 'Maximum allowed similarity level between consecutive email alerts',
        '%field2' => 'Email alerts should be considered "consecutive" if sent within',
      ]));
    }
    if ($userInputValues['emaillog_max_similarity_level']) {
      if (!is_numeric($userInputValues['emaillog_max_similarity_level'])) {
        $form_state->setErrorByName('emaillog_max_similarity_level', $this->t('Value of %field cannot contain any non-numeric characters.', [
          '%field' => 'Maximum allowed similarity level between consecutive email alerts',
        ]));
      }
      if ($userInputValues['emaillog_max_similarity_level'] < 0 || $userInputValues['emaillog_max_similarity_level'] > 1) {
        $form_state->setErrorByName('emaillog_max_similarity_level', $this->t('Value of %field needs to be in [0-1] range.', [
          '%field' => 'Maximum allowed similarity level between consecutive email alerts',
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $userInputValues = $form_state->getUserInput();
    $config = $this->config('emaillog.settings');

    $severity_levels = RfcLogLevel::getLevels();

    $debug_info = [];
    foreach (array_keys($severity_levels) as $level_id) {
      foreach (array_keys(_emaillog_get_debug_info_callbacks()) as $variable_id) {
        if (!empty($userInputValues['debug_info']['variables'][$variable_id][$level_id])) {
          $debug_info[$level_id][$variable_id] = 1;
        }
      }
    }

    foreach (array_keys($severity_levels) as $level_id) {
      $config->set('emaillog_' . $level_id, $userInputValues['emaillog_' . $level_id]);
      $config->set('emaillog_' . $level_id . '_channels', $userInputValues['emaillog_' . $level_id . '_channels']);
    }

    // Unchecked checkboxes are missing from the user input.
    $config->set('emaillog_backtrace_replace_args', !empty($userInputValues['debug_info']['emaillog_backtrace_replace_args']));
    $config->set('emaillog_debug_info', $debug_info);
    $config->set('emaillog_max_similar_emails', $userInputValues['emaillog_max_similar_emails']);
    $config->set('emaillog_max_consecutive_timespan', $userInputValues['emaillog_max_consecutive_timespan']);
    $config->set('emaillog_max_similarity_level', $userInputValues['emaillog_max_similarity_level']);
    $config->set('emaillog_legacy_subject', !empty($userInputValues['emaillog_legacy_subject']));
    $config->set('emaillog_custom_subject', trim($userInputValues['emaillog_custom_subject']));

    $config->save();
  }

}
