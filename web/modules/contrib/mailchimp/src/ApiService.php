<?php

declare(strict_types=1);

namespace Drupal\mailchimp;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Language\ContextProvider\CurrentLanguageContext;
use Drupal\Core\Link;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use GuzzleHttp\Exception\ClientException;
use Mailchimp\MailchimpLists;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Access point for interacting with the Mailchimp API.
 */
final class ApiService {

  /**
   * Constructs an ApiService object.
   *
   * @param \Drupal\mailchimp\ClientFactory $mailchimpClientFactory
   *   The Mailchimp client factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyvalue
   *   The key/value store factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheMailchimp
   *   The Mailchimp cache backend.
   * @param \Psr\Log\LoggerInterface $logger
   *   The Mailchimp logger channel.
   * @param \Drupal\Core\Language\ContextProvider\CurrentLanguageContext $languageCurrentLanguageContext
   *   The current language context provider.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Lock\LockBackendInterface $lockBackend
   *   The lock backend.
   */
  public function __construct(
    private ClientFactory $mailchimpClientFactory,
    private MessengerInterface $messenger,
    private KeyValueFactoryInterface $keyvalue,
    private CacheBackendInterface $cacheMailchimp,
    private LoggerInterface $logger,
    private CurrentLanguageContext $languageCurrentLanguageContext,
    private ModuleHandlerInterface $moduleHandler,
    private ConfigFactoryInterface $configFactory,
    private LockBackendInterface $lockBackend,
  ) {}

  /**
   * Instantiates a Mailchimp library object.
   *
   * @param string $classname
   *   A valid \Mailchimp\MailchimpApiUser class name.
   * @param bool $notify
   *   Set to FALSE to suppress the user-facing messenger error on failure,
   *   e.g. for background checks (cron) that have no interactive page to
   *   display it on.
   *
   * @return \Mailchimp\MailchimpApiUser|null
   *   Drupal Mailchimp library object, or NULL if it could not be loaded or
   *   the API is not accessible.
   */
  public function getApiObject(string $classname = 'MailchimpApiUser', bool $notify = TRUE) {
    $object = $this->mailchimpClientFactory->getByClassNameOrNull($classname);
    // Returning mailchimpapiuser.
    if (!$object) {
      if ($notify) {
        $this->messenger->addError('Failed to load Mailchimp PHP library. Please refer to the installation requirements.');
      }
      return NULL;
    }
    $config = $this->configFactory->get('mailchimp.settings');
    if (!$config->get('test_mode') && !$object->hasApiAccess()) {
      if ($notify) {
        $mc_oauth_url = Url::fromRoute('mailchimp.admin.oauth');
        $this->messenger->addError(t('Unable to connect to Mailchimp API. Visit @oauth_settings_page to authenticate or uncheck "Use OAuth Authentication" and add an api_key below (deprecated).',
          [
            '@oauth_settings_page' => Link::fromTextAndUrl(t('OAuth Settings page'), $mc_oauth_url)->toString(),
          ]));
      }
      return NULL;
    }

    return $object;
  }

  /**
   * Returns all Mailchimp audiences for a given account.
   *
   * Optionally limit audiences to those with the given IDs. Audiences are
   * stored in a collection.
   *
   * @param array $audience_ids
   *   An array of audience IDs to filter the results by.
   * @param bool $reset
   *   Force a refresh of the audiences from Mailchimp.
   *
   * @return array
   *   An array of audience data objects.
   */
  public function getAudiences(array $audience_ids = [], bool $reset = FALSE) : array {
    $collection = $this->keyvalue->get('mailchimp_lists');
    $audiences = $reset ? [] : $collection->get('lists', []);

    // If we have no stored audiences, or we are forcing a refresh, get them
    // from Mailchimp.
    if ($audiences === []) {
      try {
        /** @var \Mailchimp\MailchimpLists $mc_lists */
        $mc_lists = $this->getApiObject('MailchimpLists');
        if ($mc_lists != NULL) {
          $result = $mc_lists->getLists(['count' => 500]);

          if ($result->total_items > 0) {
            foreach ($result->lists as $list) {
              $int_category_data = $mc_lists->getInterestCategories($list->id, ['count' => 500]);
              if ($int_category_data->total_items > 0) {

                $list->intgroups = [];
                foreach ($int_category_data->categories as $interest_category) {
                  $interest_data = $mc_lists->getInterests($list->id, $interest_category->id, ['count' => 500]);

                  if ($interest_data->total_items > 0) {
                    $interest_category->interests = $interest_data->interests;
                  }

                  $list->intgroups[] = $interest_category;
                }
              }

              $audiences[$list->id] = $list;

              // Append mergefields:
              $mergefields = $mc_lists->getMergeFields($list->id, ['count' => 500]);
              if ($mergefields->total_items > 0) {
                $audiences[$list->id]->mergevars = $mergefields->merge_fields;
              }
            }
          }

          uasort($audiences, '_mailchimp_list_cmp');

          if ($reset) {
            // Delete entire collection. This will also cause merge vars to be
            // refreshed when they are requested.
            // @see \Drupal\mailchimp\ApiService::getMergevars()
            $collection->deleteAll();
          }
          $collection->set('lists', $audiences);
        }
      }
      catch (\Exception $e) {
        Error::logException($this->logger, $e, 'An error occurred requesting audience information from Mailchimp. ' . Error::DEFAULT_ERROR_MESSAGE);
      }
    }

    // There was a problem getting audiences, which was probably already logged.
    if (!isset($audiences) || is_null($audiences)) {
      return [];
    }

    // Filter by given IDs.
    if (!empty($audience_ids)) {
      $filtered_lists = [];

      foreach ($audience_ids as $id) {
        if (array_key_exists($id, $audiences)) {
          $filtered_lists[$id] = $audiences[$id];
        }
      }

      return $filtered_lists;
    }
    else {
      return $audiences;
    }
  }

  /**
   * Wrapper around MailchimpLists::getMergeFields().
   *
   * @param array $audience_ids
   *   Array of Mailchimp audience IDs.
   * @param bool $reset
   *   Set to TRUE if mergevars should not be loaded from cache.
   *
   * @return array
   *   Struct describing mergevars for the specified audiences.
   */
  public function getMergevars(array $audience_ids, bool $reset = FALSE) : array {
    $mergevars = [];
    $collection = $this->keyvalue->get('mailchimp_lists');

    if (!$reset) {
      foreach ($audience_ids as $key => $audience_id) {

        $state_data = $collection->get("list_{$audience_id}_mergevars");
        // Get cached data and unset from our remaining audiences to query.
        if ($state_data) {
          $mergevars[$audience_id] = $state_data;
          unset($audience_ids[$key]);
        }
      }
    }

    // Get the uncached merge vars from Mailchimp.
    if (count($audience_ids)) {
      /** @var \Mailchimp\MailchimpLists $mc_lists */
      $mc_lists = $this->getApiObject('MailchimpLists');
      $audience_id = NULL;

      try {
        if (!$mc_lists) {
          throw new \Exception('Cannot get merge vars without Mailchimp API. Check API key has been entered.');
        }

        foreach ($audience_ids as $audience_id) {
          // Add default EMAIL merge var for all lists.
          $mergevars[$audience_id] = [
            (object) [
              'tag' => 'EMAIL',
              'name' => t('Email Address'),
              'type' => 'email',
              'required' => TRUE,
              'default_value' => '',
              'public' => TRUE,
              'display_order' => 1,
              'options' => (object) [
                'size' => 25,
              ],
            ],
          ];

          $result = $mc_lists->getMergeFields($audience_id, ['count' => 500]);

          if ($result->total_items > 0) {
            $mergevars[$audience_id] = array_merge($mergevars[$audience_id], $result->merge_fields);
          }

          $collection->set("list_{$audience_id}_mergevars", $mergevars[$audience_id]);
        }
      }

      catch (\Exception $e) {
        Error::logException($this->logger, $e, 'An error occurred requesting mergevars for audience {list}. ' . Error::DEFAULT_ERROR_MESSAGE, [
          'list' => $audience_id,
        ]);
      }
    }

    return $mergevars;
  }

  /**
   * Gets the Mailchimp member info for a given email address and audience.
   *
   * Results are cached in the cache_mailchimp bin which is cleared by the
   * Mailchimp web hooks system when needed.
   *
   * @param string $audience_id
   *   The Mailchimp audience ID to get member info for.
   * @param string $email
   *   The Mailchimp user email address to load member info for.
   * @param bool $reset
   *   Set to TRUE if member info should not be loaded from cache.
   *
   * @return object
   *   Member info object, empty if there is no valid info.
   */
  public function getMemberInfo($audience_id, $email, $reset = FALSE) : object {
    if (!$reset) {
      $cached_data = $this->cacheMailchimp->get($audience_id . '-' . $email);

      if ($cached_data) {
        return $cached_data->data;
      }
    }

    // Query audiences from the MCAPI and store in cache:
    $memberinfo = new \stdClass();

    /** @var \Mailchimp\MailchimpLists $mc_lists */
    $mc_lists = $this->getApiObject('MailchimpLists');
    try {
      if (!$mc_lists) {
        throw new \Exception('Cannot get member info without Mailchimp API. Check API key has been entered.');
      }
      $result = $mc_lists->getMemberInfo($audience_id, $email);
      if (!empty($result->id)) {
        $memberinfo = $result;
        $this->cacheMailchimp->set($audience_id . '-' . $email, $memberinfo);
      }
    }
    catch (\Exception $e) {
      // A 404 exception code means mailchimp does not have subscription
      // information for given email address. This is not an error and we can
      // cache this information.
      if ($e->getCode() == 404) {
        $this->cacheMailchimp->set($audience_id . '-' . $email, $memberinfo);
      }
      else {
        Error::logException($this->logger, $e, 'An error occurred requesting memberinfo for {email} in audience {list}. ' . Error::DEFAULT_ERROR_MESSAGE, [
          'email' => $email,
          'list' => $audience_id,
        ]);
      }
    }

    return $memberinfo;
  }

  /**
   * Wrapper around MailchimpLists::addOrUpdateMember().
   *
   * @param string $audience_id
   *   The Mailchimp audience ID.
   * @param string $email
   *   The email address to subscribe.
   * @param array $merge_vars
   *   Associative array of merge field values keyed by tag name.
   * @param array $interests
   *   Array of interest group selections, keyed by interest group ID.
   * @param bool $double_optin
   *   Set to TRUE to require double opt-in confirmation.
   * @param string $format
   *   Email format to use, "html" or "text".
   * @param string $language
   *   Language code to set for the subscriber.
   * @param bool $gdpr_consent
   *   Set to TRUE if the subscriber has given GDPR marketing consent.
   * @param string $tags
   *   Comma-separated list of tags to apply to the member.
   * @param string $segment_id
   *   ID of a segment to add the member to.
   *
   * @return bool|object
   *   An object containing member data, or FALSE if subscribing failed.
   *
   * @see Mailchimp\MailchimpLists::addOrUpdateMember()
   */
  public function subscribeProcess($audience_id, $email, $merge_vars = NULL, $interests = [], $double_optin = FALSE, $format = 'html', $language = NULL, $gdpr_consent = FALSE, $tags = NULL, $segment_id = NULL) : bool|object {
    $config = $this->configFactory->get('mailchimp.settings');
    $result = FALSE;

    try {
      /** @var \Mailchimp\MailchimpLists $mc_lists */
      $mc_lists = $this->getApiObject('MailchimpLists');
      if (!$mc_lists) {
        throw new \Exception('Cannot subscribe to audience without Mailchimp API. Check API key has been entered.');
      }

      $parameters = [
      // If double opt-in is required, set member status to 'pending', but only
      // if the user isn't already subscribed.
        'status' => ($double_optin && !mailchimp_is_subscribed($audience_id, $email)) ? MailchimpLists::MEMBER_STATUS_PENDING : MailchimpLists::MEMBER_STATUS_SUBSCRIBED,
        'email_type' => $format,
      ];

      if (!empty($language)) {
        $parameters['language'] = $language;
      }

      // Set interests.
      if (!empty($interests)) {
        $selected_interests = [];
        foreach ($interests as $interest_group) {
          if ($interest_group) {
            // This could happen if the selected interest group is set to
            // display radio inputs. So we simply transform the single string
            // value to an array in order to pass the condition check below.
            if (!is_array($interest_group)) {
              $interest_group = [$interest_group => $interest_group];
            }

            foreach ($interest_group as $interest_id => $interest_status) {
              $selected_interests[$interest_id] = ($interest_status !== 0);
            }
          }
        }

        if (!empty($selected_interests)) {
          $parameters['interests'] = (object) $selected_interests;
        }
      }

      // Set merge fields.
      if (!empty($merge_vars)) {
        $parameters['merge_fields'] = (object) $merge_vars;
      }

      // Has GDPR consent been given?
      if ($gdpr_consent) {
        // If the member is already subscribed get the marketing permission ID(s)
        // for the audience and enable them.
        $marketing_permissions = mailchimp_get_marketing_permissions($audience_id, $email);
        $was_subscribed        = FALSE;
        if ($marketing_permissions) {
          foreach ($marketing_permissions as $marketing_permission) {
            $parameters['marketing_permissions'][] = [
              'marketing_permission_id' => $marketing_permission->marketing_permission_id,
              'enabled'                 => TRUE,
            ];
          }
          $was_subscribed = TRUE;
        }
      }
      else {
        // We need to make sure this is set.
        $was_subscribed = FALSE;
      }

      // Add member to audience.
      $result = $mc_lists->addOrUpdateMember($audience_id, $email, $parameters);

      if (isset($result->id)) {
        $this->moduleHandler->invokeAll('mailchimp_subscribe_success', [
          $audience_id,
          $email,
          $merge_vars,
        ]);

        // Clear user cache, just in case there's some cruft leftover:
        mailchimp_cache_clear_member($audience_id, $email);

        $this->logger->notice('{email} was subscribed to audience {list}.', [
          'email' => $email,
          'list' => $audience_id,
        ]);

        // For newly subscribed members set GDPR consent if it's been given.
        if (!$was_subscribed && $gdpr_consent && !empty($result->marketing_permissions)) {
          // If the member is already subscribed get the marketing permission
          // ID(s) for the audience and enable them.
          foreach ($result->marketing_permissions as $marketing_permission) {
            $parameters['marketing_permissions'][] = [
              'marketing_permission_id' => $marketing_permission->marketing_permission_id,
              'enabled'                 => TRUE,
            ];
          }
          // Update the member.
          $result = $mc_lists->addOrUpdateMember($audience_id, $email, $parameters);
          if (!isset($result->id)) {
            $this->logger
              ->warning('A problem occurred setting marketing permissions for {email} on audience {list}.', [
                'email' => $email,
                'list'  => $audience_id,
              ]);
          }
        }

        if ($double_optin) {
          $msg = $config->get('optin_check_email_msg');
          if ($msg) {
            $this->messenger->addStatus($msg, FALSE);
          }
        }

        // Add or update member tags.
        if ($tags) {
          $tags = explode(',', (string) $tags);
          $tags = array_map('trim', $tags);

          try {
            $mc_lists->addTagsMember($audience_id, $tags, $email);
          }

          catch (ClientException $e) {
            Error::logException($this->logger, $e, 'An error occurred while adding tags for this email({email}) to Mailchimp: ' . Error::DEFAULT_ERROR_MESSAGE, [
              'email' => $email,
            ]);
          }
        }

        // Add or update member segments.
        if ($segment_id) {
          try {
            $result_segment = $mc_lists->addSegmentMember($audience_id, $segment_id, $email, $parameters);

            if (!isset($result_segment->id)) {
              $this->logger->warning('A problem occurred setting segment {segment_id} for {email} on audience {list}.', [
                'segment_id' => $segment_id,
                'email' => $email,
                'list'  => $audience_id,
              ]);
            }
          }
          catch (ClientException $e) {
            Error::logException($this->logger, $e, 'An error occurred while adding segment for this email ({email}) to Mailchimp: {message}' . Error::DEFAULT_ERROR_MESSAGE, [
              'message' => $e->getMessage(),
              'email' => $email,
            ]);
          }
        }
      }
      else {
        // Mailchimp returned a response without a member ID, meaning the
        // subscribe did not actually take effect. Treat this as a failure
        // so callers (e.g. the queue processor) don't mistake it for
        // success and discard a queued retry.
        $result = FALSE;
        if (!$config->get('test_mode')) {
          $this->logger->warning('A problem occurred subscribing {email} to audience {list}.', [
            'email' => $email,
            'list' => $audience_id,
          ]);
        }
      }
    }
    catch (\Exception $e) {
      if ($e->getCode() == '400' && strpos($e->getMessage(), 'Member In Compliance State') !== FALSE && !$double_optin) {
        Error::logException($this->logger, $e, 'Detected "Member In Compliance State" subscribing {email} to audience {list}. Trying again using double-opt in.' . Error::DEFAULT_ERROR_MESSAGE, [
          'email' => $email,
          'list' => $audience_id,
        ], LogLevel::NOTICE);
        return $this->subscribeProcess($audience_id, $email, $merge_vars, $interests, TRUE, $format, $language, $gdpr_consent, $tags);
      }

      $log_level = LogLevel::ERROR;
      // Mailchimp API validation errors should not be considered Drupal errors
      // because they are unpredictable and not fixable from the Drupal side.
      // Therefore, reduce the log level for those.
      if ($e->getCode() == '400' && strpos($e->getMessage(), 'Invalid Resource') !== FALSE) {
        $log_level = LogLevel::NOTICE;
      }
      Error::logException($this->logger, $e, 'An error occurred subscribing {email} to audience {list}. "{message}"' . Error::DEFAULT_ERROR_MESSAGE, [
        'email' => $email,
        'list' => $audience_id,
        'message' => $e->getMessage(),
      ], $log_level);

    }

    return $result;
  }

  /**
   * Wrapper around MailchimpLists::updateMember().
   *
   * @param string $audience_id
   *   The Mailchimp audience ID.
   * @param string $email
   *   The email address of the member to update.
   * @param array $merge_vars
   *   Associative array of merge field values keyed by tag name.
   * @param array $interests
   *   Array of interest group selections, keyed by interest group ID.
   * @param string $format
   *   Email format to use, "html" or "text".
   * @param bool $double_optin
   *   Set to TRUE to require double opt-in confirmation.
   * @param bool $gdpr_consent
   *   Set to TRUE if the subscriber has given GDPR marketing consent.
   * @param string $tags
   *   Comma-separated list of tags to apply to the member.
   *
   * @return bool|object
   *   An object containing member data, or FALSE if the update failed.
   *
   * @see Mailchimp\MailchimpLists::updateMember()
   */
  public function updateMemberProcess($audience_id, $email, $merge_vars, $interests, $format, $double_optin = FALSE, $gdpr_consent = FALSE, $tags = NULL) : bool|object {
    $config = $this->configFactory->get('mailchimp.settings');
    $result = FALSE;

    try {
      /** @var \Mailchimp\MailchimpLists $mc_lists */
      $mc_lists = $this->getApiObject('MailchimpLists');

      $parameters = [
        'status' => ($double_optin) ? MailchimpLists::MEMBER_STATUS_PENDING : MailchimpLists::MEMBER_STATUS_SUBSCRIBED,
        'email_type' => $format,
      ];

      // Set interests.
      if (!empty($interests)) {
        $selected_interests = [];
        foreach ($interests as $interest_group) {
          // This could happen in case the selected interest group
          // is set to display radio inputs. So we either do an
          // explicit check here, or simply transform the single string
          // value to an array in order to pass the condition check below.
          if (!is_array($interest_group)) {
            $interest_group = [$interest_group => $interest_group];
          }

          foreach ($interest_group as $interest_id => $interest_status) {
            $selected_interests[$interest_id] = ($interest_status !== 0);
          }
        }

        if (!empty($selected_interests)) {
          $parameters['interests'] = (object) $selected_interests;
        }
      }

      // Set merge fields.
      if (!empty($merge_vars)) {
        $parameters['merge_fields'] = (object) $merge_vars;
      }

      // Has GDPR consent been given?
      if ($gdpr_consent) {
        // If the member is already subscribed get the marketing permission ID(s)
        // for the audience and enable them.
        $marketing_permissions = mailchimp_get_marketing_permissions($audience_id, $email);
        if ($marketing_permissions) {
          foreach ($marketing_permissions as $marketing_permission) {
            $parameters['marketing_permissions'][] = [
              'marketing_permission_id' => $marketing_permission->marketing_permission_id,
              'enabled'                 => TRUE,
            ];
          }
        }
      }

      // Update member.
      $result = $mc_lists->updateMember($audience_id, $email, $parameters);

      if (isset($result->id)) {
        $this->logger->notice('{email} was updated in audience {list}.', [
          'email' => $email,
          'list' => $audience_id,
        ]);

        // Clear user cache:
        mailchimp_cache_clear_member($audience_id, $email);
      }
      else {
        // Mailchimp returned a response without a member ID, meaning the
        // update did not actually take effect. Treat this as a failure so
        // callers (e.g. the queue processor) don't mistake it for success
        // and discard a queued retry.
        $result = FALSE;
        $this->logger->warning('A problem occurred updating {email} in audience {list}.', [
          'email' => $email,
          'list' => $audience_id,
        ]);
      }
    }
    catch (\Exception $e) {
      if ($e->getCode() == '400' && strpos($e->getMessage(), 'Member In Compliance State') !== FALSE && !$double_optin) {
        Error::logException($this->logger, $e, 'Detected "Member In Compliance State" subscribing {email} to audience {list}.  Trying again using double-opt in.' . Error::DEFAULT_ERROR_MESSAGE, [
          'email' => $email,
          'list' => $audience_id,
        ], LogLevel::NOTICE);

        return $this->updateMemberProcess($audience_id, $email, $merge_vars, $interests, $format, TRUE, $gdpr_consent, $tags);
      }

      // A 404 exception code means mailchimp does not have subscription
      // information for given email address. This is not an error.
      if ($e->getCode() !== 404) {
        Error::logException($this->logger, $e, 'An error occurred updating {email} in audience {list}. ' . Error::DEFAULT_ERROR_MESSAGE, [
          'email' => $email,
          'list' => $audience_id,
        ]);
      }
    }

    if ($double_optin) {
      $msg = $config->get('optin_check_email_msg');
      if ($msg) {
        $this->messenger->addStatus($msg, FALSE);
      }
    }

    // Add or update member tags.
    if ($tags) {
      $tags = explode(',', (string) $tags);
      $tags = array_map('trim', $tags);

      try {
        $mc_lists->addTagsMember($audience_id, $tags, $email);
      }

      catch (ClientException $e) {
        Error::logException($this->logger, $e, 'An error occurred while adding tags for this email({email}) to Mailchimp: ' . Error::DEFAULT_ERROR_MESSAGE, [
          'email' => $email,
        ]);
      }
    }

    return $result;
  }

  /**
   * Retrieves all members of a given audience with a given status.
   *
   * Note that this function can cause locking and is somewhat slow. It is not
   * recommended unless you know what you are doing! See the MCAPI documentation.
   *
   * @param string $audience_id
   *   The Mailchimp audience ID.
   * @param string $status
   *   The subscription status.
   * @param array $options
   *   Options for retrieving the list of members.
   *
   * @return bool|object
   *   An object containing member data or FALSE if it fails to acquire the
   *   lock.
   *
   * @see https://mailchimp.com/developer/marketing/api/list-members/list-members-info
   */
  public function getMembers($audience_id, $status = MailchimpLists::MEMBER_STATUS_SUBSCRIBED, array $options = []) : bool|object {
    $results = FALSE;

    if ($this->lockBackend->acquire('mailchimp_get_members', 60)) {
      try {
        /** @var \Mailchimp\MailchimpLists $mc_lists */
        $mc_lists = $this->getApiObject('MailchimpLists');

        $options['status'] = $status;
        if (!isset($options['count'])) {
          $options['count'] = 500;
        }

        $results = $mc_lists->getMembers($audience_id, $options);
      }
      catch (\Exception $e) {
        Error::logException($this->logger, $e, 'An error occurred pulling member info for an audience. ' . Error::DEFAULT_ERROR_MESSAGE);
      }

      $this->lockBackend->release('mailchimp_get_members');
    }

    return $results;
  }

  /**
   * Batch updates a number of Mailchimp audience members.
   *
   * @param string $audience_id
   *   The Mailchimp audience ID.
   * @param array $batch
   *   A list of data for each member being updated.
   *
   * @return bool|object
   *   An object describing the batch status or FALSE if an error occurred.
   *
   * @see Mailchimp\MailchimpApiUser::processBatchOperations()
   */
  public function batchUpdateMembers($audience_id, array $batch) : bool|object {
    $results = FALSE;

    try {
      /** @var \Mailchimp\MailchimpLists $mc_lists */
      $mc_lists = $this->getApiObject('MailchimpLists');
      if (!$mc_lists) {
        throw new \Exception('Cannot batch subscribe to audience without Mailchimp API. Check API key has been entered.');
      }

      if (!empty($batch)) {
        // Create a new batch update operation for each member.
        foreach ($batch as $batch_data) {
          // @todo Remove 'advanced' earlier? Needed at all?
          unset($batch_data['merge_vars']['advanced']);

          $parameters = [
            'email_type' => $batch_data['email_type'],
            'merge_fields' => (object) $batch_data['merge_vars'],
          ];

          $mc_lists->addOrUpdateMember($audience_id, $batch_data['email'], $parameters, TRUE);
        }

        // Process batch operations.
        return $mc_lists->processBatchOperations();
      }
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e, 'An error occurred performing batch subscribe/update. ' . Error::DEFAULT_ERROR_MESSAGE);
    }

    return $results;
  }

  /**
   * Unsubscribes a member from a Mailchimp audience.
   *
   * @param string $audience_id
   *   The Mailchimp audience ID.
   * @param string $email
   *   The user email address unsubscribe.
   *
   * @return bool
   *   TRUE if successfully unsubscribed; FALSE otherwise.
   *
   * @see Mailchimp\MailchimpLists::updateMember()
   */
  public function unsubscribeProcess($audience_id, $email) : bool {
    try {
      /** @var \Mailchimp\MailchimpLists $mc_lists */
      $mc_lists = $this->getApiObject('MailchimpLists');
      if (!$mc_lists) {
        throw new \Exception('Cannot unsubscribe from audience without Mailchimp API. Check API key has been entered.');
      }

      if (!mailchimp_is_subscribed($audience_id, $email)) {
        return TRUE;
      }

      $mc_lists->updateMember($audience_id, $email, ['status' => MailchimpLists::MEMBER_STATUS_UNSUBSCRIBED]);

      $this->moduleHandler->invokeAll('mailchimp_unsubscribe_success', [
        $audience_id,
        $email,
      ]);

      // Clear user cache:
      mailchimp_cache_clear_member($audience_id, $email);

      return TRUE;
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e, 'An error occurred unsubscribing {email} from audience {list}. ' . Error::DEFAULT_ERROR_MESSAGE, [
        'email' => $email,
        'list' => $audience_id,
      ]);
    }

    return FALSE;
  }

  /**
   * Wrapper function to return data for a given campaign.
   *
   * Data is stored in the Mailchimp cache.
   *
   * @param string $campaign_id
   *   The ID of the campaign to get data for.
   * @param bool $reset
   *   Set to TRUE if campaign data should not be loaded from cache.
   *
   * @return object|bool
   *   Campaign data object, or FALSE if not found.
   */
  public function getCampaignData($campaign_id, $reset = FALSE) : object|bool {
    $campaign_data = FALSE;

    if (!$reset) {
      $cached_data = $this->cacheMailchimp->get('campaign_' . $campaign_id);

      if ($cached_data) {
        return $cached_data->data;
      }
    }

    try {
      /** @var \Mailchimp\MailchimpCampaigns $mcapi */
      $mcapi = $this->getApiObject('MailchimpCampaigns');

      $response = $mcapi->getCampaign($campaign_id);

      if (!empty($response->id)) {
        $campaign_data = $response;
        $this->cacheMailchimp->set('campaign_' . $campaign_id, $campaign_data);
      }
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e, 'An error occurred retrieving campaign data for {campaign}. ' . Error::DEFAULT_ERROR_MESSAGE, [
        'campaign' => $campaign_id,
      ]);
    }

    return $campaign_data;
  }

  /**
   * Returns all audiences a given email address is currently subscribed to.
   *
   * @param string $email
   *   Email address to search.
   *
   * @return array
   *   Campaign structs containing id, web_id, name.
   */
  public function getAudiencesForEmail($email) : array {
    try {
      /** @var \Mailchimp\MailchimpLists $mc_lists */
      $mc_lists = $this->getApiObject('MailchimpLists');
      $audiences = $mc_lists->getListsForEmail($email);
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e, 'An error occurred retreiving audience data for {email}. ' . Error::DEFAULT_ERROR_MESSAGE, [
        'email' => $email,
      ]);
      $audiences = [];
    }

    return $audiences;
  }

  /**
   * Returns all webhooks for a given Mailchimp audience ID.
   *
   * @param string $audience_id
   *   The Mailchimp audience ID.
   *
   * @return bool|array
   *   An array containing information about webhooks or FALSE if no webhooks
   *   returned.
   *
   * @see Mailchimp\MailchimpLists::getWebhooks()
   */
  public function webhookGet($audience_id) : bool|array {
    try {
      /** @var \Mailchimp\MailchimpLists $mc_lists */
      $mc_lists = $this->getApiObject('MailchimpLists');
      $result = $mc_lists->getWebhooks($audience_id);

      return ($result->total_items > 0) ? $result->webhooks : FALSE;
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e, 'An error occurred reading webhooks for audience {list}. ' . Error::DEFAULT_ERROR_MESSAGE, [
        'list' => $audience_id,
      ]);
      return FALSE;
    }
  }

  /**
   * Adds a webhook to a Mailchimp audience.
   *
   * @param string $audience_id
   *   The Mailchimp audience ID to add a webhook for.
   * @param string $url
   *   The URL of the webhook endpoint.
   * @param array $events
   *   Associative array of event name to bool, indicating whether they are
   *   enabled.
   * @param array $sources
   *   Associative array of source name to bool, indicating whether they are
   *   enabled.
   *
   * @return string|bool
   *   The ID of the new webhook, or FALSE if an error occurred.
   *
   * @see Mailchimp\MailchimpLists::addWebhook()
   */
  public function webhookAdd($audience_id, $url, array $events = [], array $sources = []) : string|bool {
    try {
      /** @var \Mailchimp\MailchimpLists $mc_lists */
      $mc_lists = $this->getApiObject('MailchimpLists');
      if (!$mc_lists) {
        throw new \Exception('Cannot add webhook without Mailchimp API. Check API key has been entered.');
      }

      $parameters = [
        'events' => (object) $events,
        'sources' => (object) $sources,
      ];

      $result = $mc_lists->addWebhook($audience_id, $url, $parameters);

      return $result->id;
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e, 'An error occurred adding webhook for audience {list}. ' . Error::DEFAULT_ERROR_MESSAGE, [
        'list' => $audience_id,
      ]);
      return FALSE;
    }
  }

  /**
   * Deletes a Mailchimp audience webhook.
   *
   * @param string $audience_id
   *   The ID of the Mailchimp audience to delete the webhook from.
   * @param string $url
   *   The URL of the webhook endpoint.
   *
   * @return bool
   *   TRUE if deletion was successful, FALSE otherwise.
   *
   * @see Mailchimp\MailchimpLists::deleteWebhook()
   */
  public function webhookDelete($audience_id, $url) : bool {
    try {
      /** @var \Mailchimp\MailchimpLists $mc_lists */
      $mc_lists = $this->getApiObject('MailchimpLists');

      $result = $mc_lists->getWebhooks($audience_id);

      if ($result->total_items > 0) {
        foreach ($result->webhooks as $webhook) {
          if ($webhook->url == $url) {
            $mc_lists->deleteWebhook($audience_id, $webhook->id);
            return TRUE;
          }
        }
      }

      return FALSE;
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e, 'An error occurred deleting webhook for audience {list}. ' . Error::DEFAULT_ERROR_MESSAGE, [
        'list' => $audience_id,
      ]);
      return FALSE;
    }
  }

  /**
   * Helper function to generate form elements for a audience's interest groups.
   *
   * @param object $audience
   *   Mailchimp audience object as returned by mailchimp_get_list().
   * @param array $defaults
   *   Array of default values to use if no group subscription values already
   *   exist at Mailchimp.
   * @param string $email
   *   Optional email address to pass to the MCAPI and retrieve existing values
   *   for use as defaults.
   * @param string $mode
   *   Elements display mode:
   *     - "default" shows all groups except the hidden ones,
   *     - "admin" shows all groups including hidden ones,
   *     - "hidden" generates '#type' => 'value' elements using default values.
   *
   * @return array
   *   A collection of form elements, one per interest group.
   */
  public function interestGroupFormElements($audience, array $defaults = [], $email = NULL, $mode = 'default') : array {
    $return = [];

    if ($mode == 'hidden') {
      foreach ($audience->intgroups as $group) {
        $return[$group->id] = [
          '#type' => 'value',
          '#value' => $defaults[$group->id] ?? [],
        ];
      }
      return $return;
    }

    try {
      $collection = $this->keyvalue->get('mailchimp_lists');

      foreach ($audience->intgroups as $group) {
        $collection_data = $collection->get("list_{$audience->id}_$group->id");
        if ($collection_data) {
          $interest_data = $collection_data;
        }
        else {
          /** @var \Mailchimp\MailchimpLists $mc_lists */
          $mc_lists = $this->getApiObject('MailchimpLists');
          $interest_data = $mc_lists->getInterests($audience->id, $group->id, ['count' => 500]);
          $collection->set("list_{$audience->id}_$group->id", $interest_data);
        }

        if (!empty($email)) {
          $memberinfo = $this->getMemberInfo($audience->id, $email);
        }

      // phpcs:disable
      $field_title = t($group->title);
      // phpcs:enable
        $field_description = NULL;
        $value = NULL;

        // Set the form field type:
        switch ($group->type) {
          case 'hidden':
            $field_title .= ' (' . t('hidden') . ')';
            $field_description = t('This group will not be visible to the end user. However you can set the default value and it will be actually used.');
            if ($mode == 'admin') {
              $field_type = 'checkboxes';
            }
            else {
              $field_type = 'value';
              $value = $defaults[$group->id] ?? [];
            }
            break;

          case 'radio':
            $field_type = 'radios';
            break;

          case 'dropdown':
            $field_type = 'select';
            break;

          default:
            $field_type = $group->type;
        }

        // Extract the field options:
        $options = [];
        if ($field_type == 'select') {
          $options[''] = '-- select --';
        }

        $default_values = [];

        // Set interest options and default values.
        foreach ($interest_data->interests as $interest) {
          // phpcs:disable
          $options[$interest->id] = t($interest->name);
          // phpcs:enable

          if (isset($memberinfo)) {
            if (isset($memberinfo->interests->{$interest->id}) && ($memberinfo->interests->{$interest->id} === TRUE)) {
              $default_values[$group->id][] = $interest->id;
            }
          }
          elseif (!empty($defaults)) {
            if ($group->type === 'radio') {
              if (isset($defaults[$group->id]) && $defaults[$group->id] === $interest->id) {
                $default_values[$group->id] = $interest->id;
              }
            }
            else {
              if (isset($defaults[$group->id][$interest->id]) && !empty($defaults[$group->id][$interest->id])) {
                $default_values[$group->id][] = $interest->id;
              }
            }
          }
        }

        $default_value = $default_values[$group->id] ?? [];
        if (in_array($field_type, ['radios', 'select'])) {
          // Make sure default value for radios or select is a string.
          if ($default_value) {
            if (is_array($default_value)) {
              $default_value = reset($default_value);
            }
          }
          else {
            $default_value = '';
          }
        }

        $return[$group->id] = [
          '#type' => $field_type,
          '#title' => $field_title,
          '#description' => $field_description,
          '#options' => $options,
          '#default_value' => $default_value,
          '#attributes' => ['class' => ['mailchimp-newsletter-interests-' . $audience->id]],
        ];
        if ($value !== NULL) {
          $return[$group->id]['#value'] = $value;
        }
      }
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e, 'An error occurred generating interest group lists. ' . Error::DEFAULT_ERROR_MESSAGE);
    }
    return $return;
  }

}
