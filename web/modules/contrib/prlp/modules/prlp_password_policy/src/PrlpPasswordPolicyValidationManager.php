<?php

namespace Drupal\prlp_password_policy;

use Drupal\password_policy\PasswordPolicyValidationManager;

/**
 * Override Password Policy module's validation manager.
 *
 * We want it to check on both the user profile and the password reset forms.
 */
class PrlpPasswordPolicyValidationManager extends PasswordPolicyValidationManager {

  /**
   * The current logged in user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The password policy storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $passwordPolicyStorage;

  /**
   * Config for user.settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $userSettingsConfig;

  /**
   * Current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function tableShouldBeVisible() {
    if (
      $this->currentUser->isAnonymous()
      && $this->userSettingsConfig->get('verify_mail')
      && !in_array(
        $this->routeMatch->getRouteName(), ['user.reset', 'user.reset.form']
      )
    ) {
      return FALSE;
    }

    // User isn't logged in while resetting password, so get uid from route.
    if ($uid = $this->routeMatch->getParameter('uid')) {
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    }
    else {
      $user = $this->currentUser;
    }

    $role_applies = $this->passwordPolicyStorage->getQuery()
      ->condition('roles.*', $user->getRoles(), 'IN')
      ->condition('show_policy_table', TRUE)
      ->accessCheck(FALSE)
      ->execute();
    return !empty($role_applies);
  }

  /**
   * {@inheritdoc}
   */
  public function validationShouldRun() {
    if (
      $this->currentUser->isAnonymous()
      && $this->userSettingsConfig->get('verify_mail')
      && !in_array(
        $this->routeMatch->getRouteName(), ['user.reset', 'user.reset.form']
      )
    ) {
      return FALSE;
    }

    // User isn't logged in while resetting password, so get uid from route.
    if ($uid = $this->routeMatch->getParameter('uid')) {
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    }
    else {
      $user = $this->currentUser;
    }

    $current_user_roles = $user->getRoles();
    // Before a user has registered all they have is the anonymous role,
    // which can't be targeted by a password policy rule. So also search
    // for the authenticated role, which every user will have post register.
    $current_user_roles[] = "authenticated";
    $role_applies = $this->passwordPolicyStorage->getQuery()
      ->condition('roles.*', $current_user_roles, 'IN')
      ->accessCheck(FALSE)
      ->execute();
    return !empty($role_applies);
  }

}
