/**
 * @file
 * Main js file for commerce timeslots.
 */

(function ($, Drupal) {

  /**
   * Define the list of week days for the date picker.
   */
  let days = {
    'sunday': 0,
    'monday': 1,
    'tuesday': 2,
    'wednesday': 3,
    'thursday': 4,
    'friday': 5,
    'saturday': 6,
  };

  /**
   * The time slot ajax processing endpoint.
   */
  let endpoint = '/ajax/commerce-timeslots/get-availability/';

  Drupal.behaviors.date = {
    attach: function attach(context, settings) {

      $(once('datePicker', 'input[data-drupal-date-format][data-timeslot_id]', context)).each(function () {
        let $input = $(this);
        let datepickerSettings = {};
        let dateFormat = $input.data('drupalDateFormat');
        $input.attr('type', 'text');

        datepickerSettings.dateFormat = dateFormat.replace('Y', 'yy').replace('m', 'mm').replace('d', 'dd');

        if ($input.attr('min')) {
          datepickerSettings.minDate = $input.attr('min');
        }
        if ($input.attr('max')) {
          datepickerSettings.maxDate = $input.attr('max');
        }
        // First, we map timeslot date fields.
        if ($input.hasClass('timeslots-date')) {
          // Get the time range element name.
          let elementName = $input.parents('.timeslots-date').next().find('select').attr('name');
          // Get the element id.
          let elementId = $input.parents('.timeslots-date').next().attr('id');

          // Now, let's attach a trigger method before show.
          datepickerSettings.beforeShowDay = function (date) {
            let day = date.getDay();
            let showDay = false;

            // Show only configured days from the "show_days" attribute. The
            // rest days must be inactive.
            $.each($(this).data('show_days'), function (dayIndex, dayName) {
              if (day === days[dayName]) {
                showDay = true;
              }
            });
            return [showDay];
          }

          // Attach the date select event.
          datepickerSettings.onSelect = function (date) {
            $input.trigger('blur');
            let orderId = $(this).data('order_id');
            let timeslotId = $(this).data('timeslot_id');
            let getParams = orderId + '/' + timeslotId + '/' + date;

            // Send a request to the server in order to check the availability.
            Drupal.ajax({ url: endpoint + elementName + '/' + elementId + '/' + getParams }).execute();
          }

          // Attach time frames processing.
          timeSlotRangesSwitch($input);
          // Do the same stuff after ajax complete event.
          $(document).ajaxComplete(function (event, xhr, settings) {
            if (settings.url.startsWith(endpoint)) {
              timeSlotRangesSwitch($input);
            }

            // Make sure we don't show html5 picker.
            $('input.timeslots-date.hasDatepicker').attr('readonly', 'readonly').on('click', function (event) {
              event.preventDefault();
              event.stopPropagation();
            });
          });
        }
        // Attach date picker config to the input.
        $input.datepicker(datepickerSettings);
      });
    },
    detach: function detach(context, settings, trigger) {
      if (trigger === 'unload') {
        $(once.filter('datePicker', 'input[data-drupal-date-format]', context)).datepicker('destroy');
      }
    }
  };

  /**
   * Do the selecting time frames processing.
   */
  function timeSlotRangesSwitch(input) {
    let time_range = input.parents('.timeslots-date').next().find('.timeslots-time-range');

    if (time_range.length) {
      // First, remove the info data element.
      input.parents('.timeslots-date').next().find('.timeslot-info-data').remove();

      let info = time_range.data('info');
      let info_element = $('<div class="timeslot-info-data"></div>');

      // Initiate the time range information before change event trigger.
      info_element.text(info[time_range.val()]);
      time_range.parents('div[id^="timeslot-time-wrapper"]').append(info_element);

      time_range.on('change', function () {
        if (info !== undefined && $(this).val() in info) {
          // First, remove the info data element.
          input.parents('.timeslots-date').next().find('.timeslot-info-data').remove();

          // Now, append it right after the select element.
          info_element = $('<div class="timeslot-info-data">' + info[$(this).val()] + '</div>');
          time_range.parents('div[id^="timeslot-time-wrapper"]').append(info_element);
        }
      });
    }
  }

})(jQuery, Drupal);
