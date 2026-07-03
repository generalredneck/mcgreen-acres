/**
 * @file
 * Shows a blocking "Processing your order" overlay during Stripe Express
 * Checkout, from the moment the customer confirms payment in the Apple
 * Pay/Google Pay sheet until the browser navigates away. Without this,
 * customers land back on the cart page while the checkout-return request
 * does its (slow, synchronous) work, and may click around and disrupt
 * their own order.
 */
((Drupal, once) => {
  const OVERLAY_ID = 'mcgreen-express-checkout-overlay';
  // Safety net: if something goes wrong server-side and the browser is never
  // redirected away, don't leave the customer stuck behind the overlay.
  const OVERLAY_TIMEOUT = 45000;

  let overlayTimeoutId = null;

  function buildOverlay() {
    let overlay = document.getElementById(OVERLAY_ID);
    if (overlay) {
      return overlay;
    }
    overlay = document.createElement('div');
    overlay.id = OVERLAY_ID;
    overlay.setAttribute('role', 'alert');
    overlay.setAttribute('aria-live', 'assertive');
    overlay.innerHTML =
      '<div class="express-checkout-overlay__box">' +
      '<div class="express-checkout-overlay__spinner" aria-hidden="true"></div>' +
      `<p>${Drupal.t('Processing your order&hellip;')}</p>` +
      `<p class="express-checkout-overlay__note">${Drupal.t("Please don't close or refresh this page.")}</p>` +
      '</div>';
    document.body.appendChild(overlay);
    return overlay;
  }

  function showOverlay() {
    buildOverlay().classList.add('is-visible');
    overlayTimeoutId = window.setTimeout(hideOverlay, OVERLAY_TIMEOUT);
  }

  function hideOverlay() {
    const overlay = document.getElementById(OVERLAY_ID);
    if (overlay) {
      overlay.classList.remove('is-visible');
    }
    if (overlayTimeoutId) {
      window.clearTimeout(overlayTimeoutId);
      overlayTimeoutId = null;
    }
  }

  Drupal.behaviors.mcgreenAcresExpressCheckoutOverlay = {
    attach(context) {
      once('mcgreen-express-checkout-overlay', 'body', context).forEach(() => {
        // commerce_stripe's own behavior creates the Express Checkout
        // instance asynchronously, so poll briefly for it instead of
        // assuming it already exists.
        const bindListeners = () => {
          const instances = Drupal.CommerceStripeExpressCheckoutInstances;
          if (!instances || !instances.size) {
            return false;
          }
          instances.forEach((instance) => {
            if (instance.expressCheckoutElement) {
              instance.expressCheckoutElement.addEventListener(
                'confirm',
                showOverlay,
              );
              instance.expressCheckoutElement.addEventListener(
                'cancel',
                hideOverlay,
              );
            }
          });
          return true;
        };

        if (!bindListeners()) {
          const interval = window.setInterval(() => {
            if (bindListeners()) {
              window.clearInterval(interval);
            }
          }, 200);
          window.setTimeout(() => window.clearInterval(interval), 10000);
        }
      });
    },
  };
})(Drupal, once);
