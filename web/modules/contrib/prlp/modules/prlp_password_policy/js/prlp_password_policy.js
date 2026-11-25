((Drupal, once) => {
  Drupal.behaviors.prlp_password_policy = {
    attach(context, settings) {
      once('prlp_password_policy', 'input.js-password-field', context).forEach(
        (passwordInput) => {
          // Move the table status div just below the password field.
          const passwordPolicyStatus = document.querySelector('#password-policy-status');
          const passwordInputParent = passwordInput.parentElement;
          passwordInputParent.appendChild(passwordPolicyStatus);
        },
      );
    },
  };
})(Drupal, once);
