/**
 * @file
 * Provides widget behaviors.
 */

(($, Drupal, once) => {
  /**
   * Handles the sms phone number element.
   *
   * @type {{attach: Drupal.behaviors.smsPhoneNumberFormElement.attach}}
   */
  Drupal.behaviors.smsPhoneNumberFormElement = {
    attach(context, settings) {
      once(
        'field-setup',
        '.sms-phone-number-field .local-number',
        context,
      ).forEach((value) => {
        const $input = $(value);
        let val = $input.val();

        $input.keyup((event) => {
          const newVal = $(event.target).val();
          if (val !== newVal) {
            val = newVal;
            $input
              .parents('.sms-phone-number-field')
              .find('.send-button')
              .addClass('show');
            $input
              .parents('.sms-phone-number-field')
              .find('.verified')
              .addClass('hide');
          }
        });
      });

      once('field-setup', '.sms-phone-number-field .country', context).forEach(
        (value) => {
          const $input = $(value);
          let val = $(value).val();
          $(value).change((event) => {
            const newVal = $(event.target).val();
            if (val !== newVal) {
              val = newVal;
              $input
                .parents('.sms-phone-number-field')
                .find('.send-button')
                .addClass('show');
              $input
                .parents('.sms-phone-number-field')
                .find('.verified')
                .addClass('hide');
            }
          });
        },
      );

      once(
        'field-setup',
        '.sms-phone-number-field .send-button',
        context,
      ).click((value) => {
        const $button = $(value);
        $button.parent().find('[type="hidden"]').val('');
      });

      if (settings.smsPhoneNumberVerificationPrompt) {
        $(
          `#${settings.smsPhoneNumberVerificationPrompt} .verification`,
        ).addClass('show');
        $(
          `#${settings.smsPhoneNumberVerificationPrompt} .verification input[type="text"]`,
        ).val('');
      }

      if (settings.smsPhoneNumberHideVerificationPrompt) {
        $(
          `#${settings.smsPhoneNumberHideVerificationPrompt} .verification`,
        ).removeClass('show');
      }

      if (settings.smsPhoneNumberVerified) {
        $(`#${settings.smsPhoneNumberVerified} .send-button`).removeClass(
          'show',
        );
        $(`#${settings.smsPhoneNumberVerified} .verified`).addClass('show');
      }
    },
  };
})(jQuery, Drupal, once);
