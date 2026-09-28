/**
 * @file
 * Handles the shipping information tabs switching.
 */
((Drupal, once) => {
  Drupal.behaviors.shippingInformation = {
    attach: function attach(context) {
      once(
        'shipping-information',
        '.shipping-information-tab',
        context,
      ).forEach((tab) => {
        tab.addEventListener('click', (e) => {
          const card = e.currentTarget.closest('.card');
          const panels = card.getElementsByClassName(
            'shipping-information-panel',
          );
          Array.prototype.forEach.call(panels, function (panel) {
            panel.classList.remove('open');
          });
          const targetIndex = e.currentTarget.dataset.contentTarget;
          const targetPanel = card.querySelector(
            `.shipping-information-panel[data-content-index="${targetIndex}"]`,
          );
          targetPanel.classList.add('open');
        });
      });
    },
  };
})(Drupal, once);
