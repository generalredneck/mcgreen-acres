/**
 * @file
 * Provides widget behaviors.
 */

(($, Drupal, once) => {
  /**
   * Handles the phone number element.
   *
   * @type {{attach: Drupal.behaviors.phoneNumberFormElement.attach}}
   */
  Drupal.behaviors.phoneNumberFormElement = {
    attach(context) {
      once('field-setup', '.phone-number-field .country', context).forEach(
        (value) => {
          const $input = $(value);
          const val = $input.val();
          $input.data('value', val);

          const addFlag = $input.hasClass('with-flag');
          const addCountryCode = $input.hasClass('with-code');

          let prefix = '<div class="prefix"></div><span class="arrow"></span>';
          if (addFlag) {
            prefix = `<div class="phone-number-flag"></div>${prefix}`;
          }

          $input.wrap('<div class="country-select"></div>').before(prefix);

          function setCountry(country) {
            if (addFlag) {
              $input
                .parents('.country-select')
                .find('.phone-number-flag')
                .removeClass($input.data('value'))
                .addClass(country.toLowerCase());
            }

            $input.data('value', country.toLowerCase());

            const callingCode = $input
              .find('option:selected')
              .text()
              .match(/\(\+\d+\)/)[0];
            const countryCode = addCountryCode ? `${country} ` : '';
            $input
              .parents('.country-select')
              .find('.prefix')
              .text(`${countryCode}${callingCode}`);
          }

          setCountry(val);

          $input.change((event) => setCountry($(event.target).val()));
        },
      );
    },
  };
})(jQuery, Drupal, once);
