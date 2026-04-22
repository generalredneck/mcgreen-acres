/**
 * @file
 * Defines behaviors for the Stripe Express Checkout Element
 * payment method form.
 */

((Drupal, drupalSettings, Stripe, once) => {
  /**
   * The Stripe Express Checkout Element Instances.
   *
   * Only one instance is supported.
   *
   * @type {Map}
   */
  Drupal.CommerceStripeExpressCheckoutInstances = new Map();

  class CommerceStripeExpressCheckoutElement {
    /**
     * The CommerceStripeExpressCheckoutElement settings.
     */
    settings;

    /**
     * The Stripe client.
     */
    stripe;

    /**
     * The stripe elements component.
     */
    elements;

    /**
     * The stripe checkout element.
     */
    expressCheckoutElement;

    /**
     * The Commerce Stripe Express Checkout element constructor.
     *
     * @param {Object} settings
     *   The settings.
     */
    constructor(settings) {
      this.settings = settings;
      // Create a Stripe client.
      this.stripe = Stripe(this.settings.publishableKey);

      // Create and mount the Express Checkout Element.
      this.elements = this.stripe.elements(this.settings.createElementsOptions);
      this.expressCheckoutElement = this.elements.create(
        'expressCheckout',
        this.settings.expressCheckoutOptions,
      );

      this.expressCheckoutElement.mount(`#${settings.elementId}`);
      // Handle the click event to pass options to the Express Checkout.
      this.expressCheckoutElement.addEventListener(
        'click',
        this.onClick.bind(this),
      );
      if (this.settings.isShippable) {
        // Listen to the shippingaddresschange event to detect when a customer
        // selects a shipping address.
        this.expressCheckoutElement.addEventListener(
          'shippingaddresschange',
          this.onShippingAddressChange.bind(this),
        );
        // Listen to the shippingratechange event to detect when a customer
        // selects a shipping rate.
        this.expressCheckoutElement.addEventListener(
          'shippingratechange',
          this.onShippingRateChange.bind(this),
        );
      }
      // Listen to the cancel event to detect when a customer dismisses the
      // payment interface.
      this.expressCheckoutElement.addEventListener(
        'cancel',
        this.onCancel.bind(this),
      );
      // Create and confirm the PaymentIntent.
      this.expressCheckoutElement.addEventListener(
        'confirm',
        this.onConfirm.bind(this),
      );
    }

    /**
     * Wrapper to post json calls.
     *
     * @param {string} url
     *   The URL of the resource.
     * @param {Object|null} data
     *   The data to post.
     * @return {Promise<any>}
     *   The fetch promise.
     */
    async postJson(url, data) {
      if (!this.settings) {
        throw new Error('Settings should be set');
      }
      if (!url) {
        throw new Error('URL parameter is required');
      }
      if (!data) {
        throw new Error('Data parameter is required');
      }
      const requestOptions = {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
      };
      try {
        requestOptions.body = JSON.stringify(data);
      } catch (e) {
        throw new Error('Invalid data format for JSON serialization');
      }

      try {
        const response = await fetch(url, requestOptions);
        if (!response.ok) {
          throw new Error(
            `HTTP error! status: ${response.status} ${response.statusText || ''}`,
          );
        }

        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
          return await response.json();
        }

        throw new Error('Response is not JSON');
      } catch (e) {
        if (e.name === 'AbortError') {
          throw new Error('Request timed out');
        }
        throw e;
      }
    }

    /**
     * The click handler.
     *
     * @param {MouseEvent} event
     *   The event.
     */
    async onClick(event) {
      const options = this.settings.onClickEventOptions;
      event.resolve(options);
    }

    /**
     * The shipping address change handler.
     *
     * @param {Event} event
     *   The event.
     */
    async onShippingAddressChange(event) {
      try {
        const data = await this.postJson(
          this.settings.shippingAddressChangeUrl,
          {
            shippingAddress: event.address,
            name: event.name,
          },
        );
        if (data.shippingRates.length) {
          this.elements.update({
            amount: data.amount,
          });
          event.resolve({
            lineItems: data.lineItems,
            shippingRates: data.shippingRates,
          });
        } else {
          event.reject();
        }
      } catch (e) {
        console.error(e);
        event.reject();
      }
    }

    /**
     * The shipping rate change handler.
     *
     * @param {Event} event
     *   The event.
     */
    async onShippingRateChange(event) {
      try {
        const data = await this.postJson(this.settings.shippingRateChangeUrl, {
          shippingRate: event.shippingRate,
        });
        if (data.shippingRates.length) {
          this.elements.update({
            amount: data.amount,
          });
          event.resolve({
            lineItems: data.lineItems,
            shippingRates: data.shippingRates,
          });
        } else {
          event.reject();
        }
      } catch (e) {
        console.error(e);
        event.reject();
      }
    }

    /**
     * The cancel handler.
     */
    async onCancel() {
      try {
        const data = await this.postJson(this.settings.cancelUrl, {});
        if (data.amount) {
          this.elements.update({
            amount: data.amount,
          });
        }
      } catch (e) {
        console.error(e);
      }
    }

    /**
     * The confirm handler.
     *
     * @param {Event} event
     *   The event.
     */
    async onConfirm(event) {
      try {
        if (this.settings.isShippable) {
          // Validate the shipping address.
          const data = await this.postJson(
            this.settings.validateShippingAddressUrl,
            {
              shippingAddress: event.shippingAddress,
            },
          );
          if (!data.isValidShippingAddress) {
            event.paymentFailed({
              reason: 'invalid_shipping_address',
            });
            return;
          }
        }
        this.elements.submit();
        // Create the PaymentIntent and obtain clientSecret.
        const data = await this.postJson(this.settings.confirmPaymentUrl, {});
        this.stripe.confirmPayment({
          elements: this.elements,
          clientSecret: data.clientSecret,
          confirmParams: {
            return_url: data.returnUrl,
          },
          redirect: 'always',
        });
      } catch (e) {
        console.error(e);
      }
    }

    /**
     * Releases resources and cleans up any associated elements or instances.
     */
    destroy() {
      if (this.expressCheckoutElement) {
        this.expressCheckoutElement.destroy();
        this.expressCheckoutElement = null;
      }
      if (this.elements) {
        this.elements.removeAllListeners();
        this.elements = null;
      }
      this.stripe = null;
    }
  }

  /**
   * Attaches the commerceStripeExpressCheckoutElement behavior.
   */
  Drupal.behaviors.commerceStripeExpressCheckout = {
    attach: (context) => {
      // Validate all required dependencies.
      if (!Drupal || !drupalSettings || !Stripe) {
        console.error('Required dependencies are not available');
        return;
      }

      const settings = drupalSettings.commerceStripeExpressCheckoutElement;
      if (!settings?.publishableKey || !settings?.elementId) {
        console.error('Required settings are missing');
        return;
      }

      const [element] = once(
        'stripe-processed',
        `#${settings.elementId}`,
        context,
      );
      if (element) {
        const checkoutElement = new CommerceStripeExpressCheckoutElement(
          settings,
        );
        Drupal.CommerceStripeExpressCheckoutInstances.set(
          element,
          checkoutElement,
        );
      }
    },
    detach: (context, settings, trigger) => {
      if (context !== document || trigger !== 'unload') {
        return;
      }
      Drupal.CommerceStripeExpressCheckoutInstances.forEach(
        (checkoutElement) => {
          checkoutElement.destroy();
        },
      );
    },
  };
})(Drupal, drupalSettings, window.Stripe, once);
