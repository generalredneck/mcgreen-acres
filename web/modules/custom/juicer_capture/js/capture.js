(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.juicerCapture = {
    attach: function (context, settings) {
      if (!drupalSettings.juicerCapture || !drupalSettings.juicerCapture.capture) {
        return;
      }
      var selector = drupalSettings.juicerCapture.selector;
      var url = drupalSettings.juicerCapture.endpoint;
      var token = drupalSettings.juicerCapture.token;
      setTimeout(function () {
        var img = once('juicer-capture', 'img[src="https://www.juicer.io/logo-without-text.svg"]', context);
        if (img.length) {

          var html = $(selector).html();
          $.ajax({
            url: url,
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            data: JSON.stringify({ html: html, token: token }),
          });
        }
      }, 2500);
    }
  };
})(jQuery, Drupal, drupalSettings);
