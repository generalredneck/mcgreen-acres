/**
 * @file
 * Provides widget behaviors.
 */

((Drupal, once) => {
  Drupal.behaviors.phoneNumberFormElement = {
    /**
     * Handles the phone number element.
     *
     * @type {{attach: Drupal.behaviors.phoneNumberFormElement.attach}}
     */
    attach(context) {
      once('field-setup', '.phone-number-field .country', context).forEach(
        (element) => {
          const val = element.value;
          element.dataset.value = val;

          const addFlag = element.classList.contains('with-flag');
          const addCountryCode = element.classList.contains('with-code');

          let prefix = '<div class="prefix"></div><span class="arrow"></span>';
          if (addFlag) {
            prefix = `<div class="phone-number-flag"></div>${prefix}`;
          }

          const wrapper = document.createElement('div');
          wrapper.className = 'country-select';
          element.parentNode.insertBefore(wrapper, element);
          wrapper.appendChild(element);
          wrapper.insertAdjacentHTML('afterbegin', prefix);

          function setCountry(country) {
            if (addFlag) {
              const countrySelect = element.closest('.country-select');
              const flag = countrySelect.querySelector('.phone-number-flag');
              if (flag && element.dataset.value) {
                flag.classList.remove(element.dataset.value);
              }
              if (flag) {
                flag.classList.add(country.toLowerCase());
              }
            }

            element.dataset.value = country.toLowerCase();

            const selectedOption =
              element.querySelector('option:checked') ||
              element.options[element.selectedIndex];
            const callingCode = selectedOption
              ? selectedOption.textContent.match(/\(\+\d+\)/)[0]
              : '';
            const countryCode = addCountryCode ? `${country} ` : '';
            const countrySelect = element.closest('.country-select');
            if (countrySelect) {
              const prefixElement = countrySelect.querySelector('.prefix');
              if (prefixElement) {
                prefixElement.textContent = `${countryCode}${callingCode}`;
              }
            }
          }

          setCountry(val);

          element.addEventListener('change', (event) =>
            setCountry(event.target.value),
          );
        },
      );
    },
  };
})(Drupal, once);
