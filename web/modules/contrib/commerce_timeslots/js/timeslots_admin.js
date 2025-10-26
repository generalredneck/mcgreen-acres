/**
 * @file
 * Main js file for commerce timeslots admin.
 */

 (function ($, Drupal, drupalSettings) {

  /**
   * Define the list of week days for the date picker.
   */
  let days = {
    0: 'sunday',
    1: 'monday',
    2: 'tuesday',
    3: 'wednesday',
    4: 'thursday',
    5: 'friday',
    6: 'saturday',
  };

  // Define the day label.
  let dayLabel = $('<p id="timeslot_day_label"></p>');

  /**
   * The commerce time slot admin js logic behavior.
   */
  Drupal.behaviors.commerceTimeslotsAdmin = {
    attach: function attach(context, settings) {
      let selected_day_type = $('select[name="timeslotday_type"]', context);

      if (selected_day_type.val() == 'desired') {
        $('div#desired-date-wrp').show();
        $('select[name="timeslot_day"]').hide();
        // Set the day label value.
        dayLabel.text($('select[name="timeslot_day"] option:selected').text());
        $('select[name="timeslot_day"]').after(dayLabel);
      }

      // Show the relevant form fields in case of day type selection.
      selected_day_type.on('change', function() {
        if ($(this).val() == 'regular') {
          $('div#desired-date-wrp').fadeOut();
          $('select[name="timeslot_day"]').show();
        }
        else {
          $('div#desired-date-wrp').fadeIn();
          $('select[name="timeslot_day"]').hide();
          // Set the day label value.
        dayLabel.text($('select[name="timeslot_day"] option:selected').text());
        $('select[name="timeslot_day"]').after(dayLabel);
        }
      });

      // Find the date picker element.
      $(context).find('input[data-drupal-date-format]').once('datePicker').each(function () {
        var $input = $(this);
        // Trigger the date chane event and set the day value.
        $input.on('change', function() {
          var desired_date = new Date($(this).val()),
              day = desired_date.getDay();

            // Set the relevant day value according to the selected desired date.
            $('select[name="timeslot_day"]').val(days[day]);
            dayLabel.text($('select[name="timeslot_day"] option:selected').text());
            $('select[name="timeslot_day"]').after(dayLabel);
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
