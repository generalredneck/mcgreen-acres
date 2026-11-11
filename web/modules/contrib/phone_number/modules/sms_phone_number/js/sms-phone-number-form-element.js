/**
 * @file
 * Provides widget behaviors.
 */

((Drupal, once) => {
  Drupal.behaviors.smsPhoneNumberFormElement = {
    /**
     * Handles the sms phone number element.
     *
     * @type {{attach: Drupal.behaviors.smsPhoneNumberFormElement.attach}}
     */
    attach(context, settings) {
      once(
        'field-setup',
        '.sms-phone-number-field .local-number',
        context,
      ).forEach((element) => {
        let val = element.value;

        element.addEventListener('keyup', (event) => {
          const newVal = event.target.value;
          if (val !== newVal) {
            val = newVal;
            const field = element.closest('.sms-phone-number-field');
            field.querySelector('.send-button').classList.add('show');
            field.querySelector('.verified').classList.add('hide');
          }
        });
      });

      once('field-setup', '.sms-phone-number-field .country', context).forEach(
        (element) => {
          let val = element.value;
          element.addEventListener('change', (event) => {
            const newVal = event.target.value;
            if (val !== newVal) {
              val = newVal;
              const field = element.closest('.sms-phone-number-field');
              field.querySelector('.send-button').classList.add('show');
              field.querySelector('.verified').classList.add('hide');
            }
          });
        },
      );

      once(
        'field-setup',
        '.sms-phone-number-field .send-button',
        context,
      ).forEach((element) => {
        element.addEventListener('click', () => {
          element.parentElement.querySelector('[type="hidden"]').value = '';
        });
      });

      if (settings.smsPhoneNumberVerificationPrompt) {
        const verification = document.querySelector(
          `#${settings.smsPhoneNumberVerificationPrompt} .verification`,
        );
        if (verification) {
          verification.classList.add('show');
        }
        const verificationInput = document.querySelector(
          `#${settings.smsPhoneNumberVerificationPrompt} .verification input[type="text"]`,
        );
        if (verificationInput) {
          verificationInput.value = '';
        }
      }

      if (settings.smsPhoneNumberHideVerificationPrompt) {
        const verification = document.querySelector(
          `#${settings.smsPhoneNumberHideVerificationPrompt} .verification`,
        );
        if (verification) {
          verification.classList.remove('show');
        }
      }

      if (settings.smsPhoneNumberVerified) {
        const sendButton = document.querySelector(
          `#${settings.smsPhoneNumberVerified} .send-button`,
        );
        if (sendButton) {
          sendButton.classList.remove('show');
        }
        const verified = document.querySelector(
          `#${settings.smsPhoneNumberVerified} .verified`,
        );
        if (verified) {
          verified.classList.add('show');
        }
      }
    },
  };
})(Drupal, once);
