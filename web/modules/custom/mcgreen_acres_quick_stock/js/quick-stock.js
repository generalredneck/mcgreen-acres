(function (Drupal, once) {
  Drupal.behaviors.mcgreenAcresQuickStock = {
    attach: function (context) {
      once('quick-stock-delta-button', '.quick-stock-delta-button', context).forEach(function (button) {
        button.addEventListener('click', function (event) {
          event.preventDefault();
          var delta = parseFloat(button.getAttribute('data-delta'));
          var form = button.closest('form');
          var input = form ? form.querySelector('.quick-stock-adjustment-input') : null;
          if (!input) {
            return;
          }
          var current = parseFloat(input.value) || 0;
          input.value = current + delta;
        });
      });
    }
  };
})(Drupal, once);
