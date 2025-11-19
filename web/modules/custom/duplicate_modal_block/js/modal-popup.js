(function (Drupal, once) {

  Drupal.behaviors.modalPopup = {
    attach: function (context, settings) {

      once('modalPopupInit', '.modal-newsletter', context).forEach(function (el) {
        // Wait 30 seconds before showing modal
        setTimeout(function () {
          if (!localStorage.getItem('newsletterModalClosed')) {
            $(el).modal('show');
          }
        }, 30000);
      });

      // Close button handler
      once('modalPopupClose', '.modal-newsletter .close-modal', context).forEach(function (btn) {
        $(btn).on('click', function () {
          localStorage.setItem('newsletterModalClosed', '1');
          $('.modal-newsletter').modal('hide');
        });
      });

    }
  };

})(Drupal, once);
