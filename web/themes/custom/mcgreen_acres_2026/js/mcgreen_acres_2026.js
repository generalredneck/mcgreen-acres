/**
 * @file
 * McGreen Acres theme JavaScript.
 * Kept minimal — Bootstrap 5 handles most interactive behavior.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Adds a drop-shadow to the header after the user scrolls past the hero.
   */
  Drupal.behaviors.mcaHeaderScroll = {
    attach(context) {
      once('mca-header-scroll', '.mca-header', context).forEach((header) => {
        const onScroll = () => {
          header.classList.toggle('shadow-sm', window.scrollY > 10);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll(); // Run once on attach.
      });
    },
  };

  /**
   * Smooth-scroll anchor links within the page.
   */
  Drupal.behaviors.mcaSmoothScroll = {
    attach(context) {
      once('mca-smooth-scroll', 'a[href^="#"]', context).forEach((link) => {
        link.addEventListener('click', (e) => {
          const target = document.querySelector(link.getAttribute('href'));
          if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        });
      });
    },
  };

}(Drupal, once));
