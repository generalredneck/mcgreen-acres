(function (document, Drupal, drupalSettings) {

  'use strict';

  Drupal.drimage_improved = {};

  Drupal.drimage_improved.webp = null;

  Drupal.drimage_improved.checkWebp = function() {
    // Prevent this function from running if the info is already available.
    if (Drupal.drimage_improved.webp !== null) {
      return;
    }
    // @see: https://developers.google.com/speed/webp/faq#how_can_i_detect_browser_support_for_webp
    var img = new Image();
    img.onload = function () {
      Drupal.drimage_improved.webp = (img.width > 0) && (img.height > 0);
    };
    img.onerror = function () {
      Drupal.drimage_improved.webp = false;
    };
    img.src = "data:image/webp;base64,UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA";
  };

  Drupal.drimage_improved.findDelayParent = function (el) {
    if (el.parentNode === null) {
      return null;
    }
    if (el.parentNode.classList && el.parentNode.classList.contains('js-delay-drimage')) {
      return el.parentNode;
    }
    return Drupal.drimage_improved.findDelayParent(el.parentNode);
  };

  Drupal.drimage_improved.resize = function (size, r, d) {
    if (size[d] === 0) {
      return size;
    }

    // Clone values into new array.
    var new_size = {
      0: size[0],
      1: size[1]
    };
    new_size[d] = r;

    var inverse_d = Math.abs(d - 1);
    if (size[inverse_d] === 0) {
      return new_size;
    }
    new_size[inverse_d] = Math.round(new_size[inverse_d] * (new_size[d] / size[d]));

    return new_size;
  };

  Drupal.drimage_improved.fetchData = function (el) {
    var data = JSON.parse(el.getAttribute("data-drimage_improved"));
    data.upscale = parseInt(data.upscale);
    data.downscale = parseInt(data.downscale);
    data.threshold = parseInt(data.threshold);
    // If no lazyload was set, assume legacy mode.
    if (!data.lazyload) {
      data.lazyload = 'lazy'; // eager
    }
    return data;
  };

  Drupal.drimage_improved.size = function (el) {
    if (el.offsetWidth === 0) {
      return { 0: 0, 1: 0 };
    }

    var data = Drupal.drimage_improved.fetchData(el);
    var size = {
      0: el.offsetWidth,
      1: 0
    };

    // Set height for aspect ratio crop.
    if (data.image_handling === 'aspect_ratio') {
      size[1] = size[0] / data.aspect_ratio.width * data.aspect_ratio.height;
    }

    // Fix blurry images when using background cover option.
    if (data.image_handling === 'background' && data.background.size === 'cover') {
      // Example: available space = 200w, 700h, original image = 1600w, 900h
      // It would be scaled to a 200w, 112h and then be stretched to 700h
      // Calculate what height we would get by using the width of the container.
      // If that calculated height is less then the container height,
      // we need to resize our width to at least that height-ratio.
      var img = el.querySelectorAll('img');
      if (img.length > 0) {
        var width = parseInt(img[0].getAttribute('width'));
        var height = parseInt(img[0].getAttribute('height'));
        var calculated_height = height / width * size[0];
        if (calculated_height < el.offsetHeight) {
          size[0] = size[0] / calculated_height * el.offsetHeight;
        }
      }
    }

    // Get the screen multiplier to deliver higher quality images.
    var multiplier = 1;
    if (data.multiplier === 1) {
      multiplier = Number(window.devicePixelRatio);
      if (isNaN(multiplier) === true || multiplier <= 0) {
        multiplier = 1;
      }
    }
    size[0] = Math.round(size[0] * multiplier);
    size[1] = Math.round(size[1] * multiplier);

    // Make sure the requested image isn't to small.
    if (size[0] < data.upscale) {
      size = Drupal.drimage_improved.resize(size, data.upscale, 0);
    }

    // Reduce all widths to a multiplier of the threshold, starting at the
    // minimal upscaling.
    var w = size[0] - data.upscale;

    var r = (Math.ceil(w / data.threshold) * data.threshold) + data.upscale;
    // When the multiplier is > 1 we can use a slightly smaller image style as
    // long as the resulting width is at least the original un-multiplied width.
    if (multiplier > 1) {
      var r_alt = (Math.floor(w / data.threshold) * data.threshold) + data.upscale;
      if (r_alt >= size[0] / multiplier) {
        r = r_alt;
      }
    }
    size = Drupal.drimage_improved.resize(size, r, 0);

    // Downscale the image if it is to large.
    if (size[0] > data.downscale) {
      size = Drupal.drimage_improved.resize(size, data.downscale, 0);
    }

    return size;
  };

  Drupal.drimage_improved.init = function (context) {
    if (typeof context === 'undefined') {
      context = document;
    }
    var el = context.querySelectorAll('.drimage:not(.is-loading)');
    if (el.length > 0) {
      for (var i = 0; i < el.length; i++) {
        var data = Drupal.drimage_improved.fetchData(el[i]);
        // Setup some properties for images that will have a fixed aspect ratio:
        if (data.image_handling === 'aspect_ratio') {
          var img = el[i].querySelectorAll('img');
          if (img.length > 0) {
            var width = parseInt(img[0].getAttribute('width'));
            var height = width / data.aspect_ratio.width * data.aspect_ratio.height;
            img[0].setAttribute('height', height);
          }
        }

        Drupal.drimage_improved.renderEl(el[i]);

        // Setup some properties for images that will render as backgrounds.
        // set class on wrapper (+css properties that are configurable)
        if (data.image_handling === 'background') {
          if (!el[i].classList.contains('is-background-image')) {
            el[i].style.backgroundAttachment = data.background.attachment;
            el[i].style.backgroundPosition = data.background.position;
            el[i].style.backgroundSize = data.background.size;
            el[i].classList.add('is-background-image');
          }
        }
      }
    }
  };

  /* @deprecated: only here for legacy settings. You should be using the html lazyloading option instead. */
  Drupal.drimage_improved.legacyLazyLoad = function (el, data) {
    var rect = el.getBoundingClientRect();
    if ((rect.top + data.lazy_offset >= 0 && rect.top - data.lazy_offset <= (window.innerHeight || document.documentElement.clientHeight)) ||
      (rect.bottom + data.lazy_offset >= 0 && rect.bottom - data.lazy_offset <= (window.innerHeight || document.documentElement.clientHeight))) {
      return true;
    }
    return false;
  };

  Drupal.drimage_improved.renderEl = function (el) {
    var delay = Drupal.drimage_improved.findDelayParent(el);
    if (delay === null) {
      var data = Drupal.drimage_improved.fetchData(el);

      var img = el.querySelector('img');
      if (data.lazyload === 'legacy') {
        // @deprecated: Legacy lazyload mode.
        if (!Drupal.drimage_improved.legacyLazyLoad(el ,data)) {
          return;
        }
      }
      else {
        img.setAttribute('loading', data.lazyload);
      }

      if (isNaN(data.fid) === false && data.fid % 1 === 0 && Number(data.fid) > 0) {
        var size = Drupal.drimage_improved.size(el);
        var w = Number(el.getAttribute('data-w'));
        var h = Number(el.getAttribute('data-h'));
        if (size[0] !== w || size[1] !== h) {
          if (size[0] > 0) {
            el.classList.add('is-loading');
            el.setAttribute('data-w', size[0]);
            el.setAttribute('data-h', size[1]);

            var imgUrl = data.subdir + '/styles/drimage_improved_';
            if (data.focal_point) {
              imgUrl += 'focal_';
            }

            var current_ratio_distortion_diff = 360;
            // Loop over drupalSettings.drimage_improved.sizes to find the best match.
            for (var i = 0; i < drupalSettings.drimage_improved.dimentions.length; i++) {
              var style = drupalSettings.drimage_improved.dimentions[i];
              if (size[0] == Number(style['width'])) {
                // Find an image style with the least amount of distortion.
                // Store the value of pi.
                var pi = Math.PI;
                var ratio_distortion =
                  (drupalSettings.drimage_improved.ratio_distortion / 60) * (pi / 180);
                var ratio = Number(style['width']) / Number(style['height']);
                var requested_ratio = size[0] / size[1];
                var calculated_ratio_distortion_diff = Math.abs(
                  Math.atan(ratio) - Math.atan(requested_ratio)
                );
                if (calculated_ratio_distortion_diff <= ratio_distortion
                  && calculated_ratio_distortion_diff < current_ratio_distortion_diff) {
                  current_ratio_distortion_diff = calculated_ratio_distortion_diff;
                  size[1] = Number(style['height']);
                }
              }
            }

            imgUrl = imgUrl + size[0] + '_' + size[1];
            // Handle image_widget_crop integration.
            if (data.image_handling === 'iwc') {
              imgUrl = imgUrl + '_' + data.iwc.image_style;
            }
            imgUrl = imgUrl + "/" + data.scheme + "/" + encodeURI(data.original_source);
            // check if imgUrl does not end in webp.
            if ((data.core_webp || data.imageapi_optimize_webp) && imgUrl.substring(imgUrl.length - 5) !== '.webp') {
              imgUrl = imgUrl + ".webp";
            }
            imgUrl = drupalSettings.path.baseUrl + imgUrl;

            setTimeout(async function() {
              try {
                // Use fetch to check if the image URL is valid before retrying.
                await fetch(imgUrl, { method: 'HEAD' })
                .then(function(response) {
                  if (response.ok) {
                    img.src = imgUrl;  // Retry loading only if the response is valid.
                  }
                  // No console warning log when response is not valid.
                })
                .catch(function(error) {
                  // No console error log when checking image availability.
                });
              } catch (error) {
                // Temporarily suppress console error messages related to 503 errors.
                const originalConsoleError = console.error;
                console.error = function (...args) {
                  if (!args[0]?.includes('503') && !args[0]?.includes('ERR_ABORTED')) {
                    originalConsoleError.apply(console, args); // Log only other errors.
                  }
                };

                // Restore original console.error after this block.
                setTimeout(() => {
                  console.error = originalConsoleError;
                }, 100);
              }
            }, 100);  // Retry after a delay of 100ms.


             img.onerror =  function () {
                setTimeout(async function() {
                  try {
                    // Use fetch to check if the image URL is valid before retrying.
                    await fetch(imgUrl, { method: 'HEAD' })
                    .then(function(response) {
                      if (response.ok) {
                        img.src = imgUrl;  // Retry loading only if the response is valid.
                      }
                      // No console warning log when response is not valid.
                    })
                    .catch(function(error) {
                      // No console error log when checking image availability.
                    });
                  } catch (error) {
                    // Temporarily suppress console error messages related to 503 errors.
                    const originalConsoleError = console.error;
                    console.error = function (...args) {
                      if (!args[0]?.includes('503') && !args[0]?.includes('ERR_ABORTED')) {
                        originalConsoleError.apply(console, args); // Log only other errors.
                      }
                    };
    
                    // Restore original console.error after this block.
                    setTimeout(() => {
                      console.error = originalConsoleError;
                    }, 100);
                  }
                }, 100);  // Retry after a delay of 100ms.
            };

            if (data.image_handling === 'background') {
              img.onload = function() {
                el.classList.remove('is-loading');
                el.style.backgroundImage = 'url("' + imgUrl + '")';
              };
              img.src = imgUrl;
            }
            else {
              if (data.core_webp || data.imageapi_optimize_webp) {
                var source = el.querySelector('source[data-format="webp"]');
                if (source) {
                  source.setAttribute('srcset', imgUrl);
                }
              }
              img.onload = function() {
                el.classList.remove('is-loading');
              };
              img.src = imgUrl;
            }
          }
        }
      }
    }
  };


  // Declare a variable for the IntersectionObserver
  let observer;

  /**
   * Callback function for the IntersectionObserver.
   * This function is called whenever an observed element enters or exits the viewport.
   * @param {IntersectionObserverEntry[]} entries - An array of IntersectionObserverEntry objects.
   */
  function handleIntersection(entries) {
    entries.forEach(entry => {
      // Check if the element is intersecting (i.e., in the viewport)
      if (entry.isIntersecting) {
        // Render the element (load the image)
        Drupal.drimage_improved.renderEl(entry.target);
        // Stop observing the element since it has already been loaded
        observer.unobserve(entry.target);
      }
    });
  }

  function handleResize() {
    const images = document.querySelectorAll('.drimage:not(.is-loading)');
    images.forEach(img => {
      observer.unobserve(img);
      observer.observe(img);
    });
  }

  let resizeTimeout;
  window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(handleResize, 100);
  });

  // Define a Drupal behavior for improved image handling
  Drupal.behaviors.drimage_improved = {
    /**
     * Attaches the behavior to elements within the specified context.
     * @param {HTMLElement} context - The context within which to search for elements.
     */
    attach: function (context) {
      // Check for WebP support (asynchronously)
      Drupal.drimage_improved.checkWebp();

      // Initialize the IntersectionObserver if it hasn't been created yet
      if (!observer) {
        observer = new IntersectionObserver(handleIntersection, {
          rootMargin: '50px', // Load images 50px before they enter the viewport
          threshold: 0.1      // Trigger the callback when 10% of the element is visible
        });
      }

      // Find all images that haven't been loaded yet
      const images = context.querySelectorAll('.drimage:not(.is-loading)');
      images.forEach(img => {
        // Start observing each image
        observer.observe(img);
      });
    }
  };

})(document, Drupal, drupalSettings);
