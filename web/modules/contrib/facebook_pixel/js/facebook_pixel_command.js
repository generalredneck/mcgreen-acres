/**
 * @file
 * facebook_pixel.js
 *
 * Defines the behavior of the facebook pixel module.
 */

(function (Drupal) {

  'use strict';

  Drupal.AjaxCommands.prototype.facebook_pixel_track = function (ajax, response, status) {
    fbq('track', response.event, response.data);
  }

}(Drupal));
