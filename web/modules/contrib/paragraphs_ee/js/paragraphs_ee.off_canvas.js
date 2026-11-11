(function ($, Drupal, drupalSettings, once) {
  /**
   * Defines a behavior to initialize the button behaviors.
   */
  Drupal.behaviors.paragraphsEeOffCanvasEvents = {
    attach(context) {
      if (!once('paragraphs-ee-off-canvas', 'html').length) {
        return;
      }
      $(window).on({
        'dialog:aftercreate': function dialogAfterCreate(
          event,
          dialog,
          $element,
          settings,
        ) {
          if (Drupal.offCanvas.isOffCanvas($element)) {
            // Initialize session storage.
            sessionStorage.setItem('paragraphsEE.OffCanvas.insertDelta', '');

            const $offCanvasDialog = $('.ui-dialog-off-canvas');
            $offCanvasDialog
              .addClass('paragraphs-ee-add-dialog')
              .addClass('paragraphs-ee-add-dialog--categorized')
              .addClass('paragraphs-ee-off-canvas')
              .addClass('paragraphs-ee-off-canvas--browser');

            const $dialogTarget = $(
              '.paragraphs-add-dialog--categorized',
              $offCanvasDialog,
            );
            const fieldName = $dialogTarget.data('dialogFieldName');
            const wrapperSelector = `edit-${fieldName.replace(/_/g, '-')}-wrapper`;
            const $dialogOriginal = $(
              `[data-drupal-selector="${
                wrapperSelector
              }"] [data-dialog-field-name="${fieldName}"].active-dialog`,
            );
            if (!$dialogOriginal) {
              return;
            }

            $(
              '.paragraphs-ee-buttons-list .paragraphs-button--add-more',
              $dialogTarget,
            ).each(function () {
              if (!once('paragraphs-ee-add-more', $(this)).length) {
                return;
              }
              const nameOriginal = $(this).attr('name');
              $(this).attr('data-triggers', nameOriginal);
              $(this).removeAttr('id');
              $(this).removeAttr('name');
              $(this).addClass('paragraphs-add-more-trigger');
            });

            Drupal.attachBehaviors($dialogTarget.get(0));
            // file.js adds a mousedown-handler to our buttons we do not want.
            $('.js-form-submit', $element).off('mousedown');
          }
        },
        'dialog:beforeClose': function dialogBeforeClose(
          event,
          dialog,
          $element,
          settings,
        ) {
          // Cleanup session storage.
          sessionStorage.removeItem('paragraphsEE.OffCanvas.insertDelta');
        },
      });
    },
  };

  Drupal.behaviors.paragraphsEeOffCanvasButtons = {
    attach(context) {
      if (!$('.ui-dialog-off-canvas')) {
        return;
      }
      const $dialog = $('.ui-dialog-off-canvas');
      $('.paragraphs-add-more-trigger', $dialog).each(function () {
        const $trigger = $(this);
        $trigger.off('click.dialog');
        $trigger.on('click.dialog', function (event) {
          const $button = $(`[name="${$trigger.data('triggers')}"]`);
          if (!$button) {
            return;
          }

          // Get the delta element before moving $element to dialog element.
          const deltaElement = $button
            .closest('.paragraphs-add-wrapper')
            .find(
              '.paragraph-type-add-modal-delta, .paragraph-type-add-delta.modal',
            )
            .get(0);
          let storedDelta = sessionStorage.getItem(
            'paragraphsEE.OffCanvas.insertDelta',
          );
          if (String(storedDelta).length && deltaElement) {
            // Set the saved delta to the form element so that the paragraph is
            // inserted at the correct location.
            deltaElement.value = storedDelta;
          }

          // Trigger mousedown event of real button.
          $button.trigger('mousedown');

          // Update delta value in storage.
          storedDelta = parseInt(deltaElement?.value, 10) + 1;
          // Save to sessionStorage.
          sessionStorage.setItem(
            'paragraphsEE.OffCanvas.insertDelta',
            storedDelta,
          );

          // Stop default execution of click event.
          event.preventDefault();
          event.stopPropagation();
        });
      });
    },
  };
})(jQuery, Drupal, drupalSettings, once);
