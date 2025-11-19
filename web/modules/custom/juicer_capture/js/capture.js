(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.juicerCapture = {
    attach: function (context, settings) {
      if (!drupalSettings.juicerCapture || !drupalSettings.juicerCapture.capture) {
        return;
      }
      var selector = drupalSettings.juicerCapture.selector;
      var url = drupalSettings.juicerCapture.endpoint;
      var token = drupalSettings.juicerCapture.token;

      var img = $('img[src="https://www.juicer.io/logo-without-text.svg"]', context).once('juicer-capture');
      if (img.length) {
        setTimeout(function () {
          var html = $(selector).html();
          $.ajax({
            url: url,
            method: 'POST',
            data: { html: html, token: token },
          });
        }, 1500);
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
