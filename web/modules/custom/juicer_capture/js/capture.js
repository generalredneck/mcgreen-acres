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
          var items = $(selector).find('li.feed-item').not('.juicer').map(function () {
            return $(this).prop('outerHTML');
          }).get();  // ← convert jQuery collection to a plain JS array
          var html = $(selector).html();
          $.ajax({
            url: url,
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            data: JSON.stringify({ items: items, token: token }),
          });
        }
      }, 2500);
    }
  };
})(jQuery, Drupal, drupalSettings);
