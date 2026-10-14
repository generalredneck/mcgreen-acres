(function ($, Drupal, drupalSettings, once) {
  /**
   * Filter items in dialog by a given search string.
   *
   * @param object $dialog
   *   The dialog to filter items.
   * @param string search
   *   The string to search for.
   * @param $dialog
   * @param search
   */
  const filterItems = function ($dialog, search) {
    if (search === '') {
      // Display all potentially hidden elements.
      $('.button-group', $dialog).removeClass('js-hide');
      $('.paragraphs-button--add-more', $dialog).removeClass('js-hide');
      return;
    }
    // Hide buttons not matching the input.
    $('.paragraphs-button--add-more', $dialog).each(function () {
      const $button = $(this);
      // Search in button label.
      let inputFound =
        $('.paragraphs-label', $button)
          .html()
          .toLowerCase()
          .indexOf(search.toLowerCase()) !== -1;
      const description = $('.paragraphs-description', $button).html() || '';
      // Search in button description.
      inputFound =
        inputFound ||
        description.toLowerCase().indexOf(search.toLowerCase()) !== -1;
      if (inputFound) {
        $button.removeClass('js-hide');
      } else {
        $button.addClass('js-hide');
      }
    });
    // Hide categories if no buttons are visible.
    $('.button-group', $dialog).each(function () {
      const $group = $(this);
      if (
        $('.paragraphs-button--add-more.js-hide', $group).length ===
        $('.paragraphs-button--add-more', $group).length
      ) {
        $group.addClass('js-hide');
      } else {
        $group.removeClass('js-hide');
      }
    });
  };

  /**
   * Init filter for paragraphs in paragraphs modal.
   */
  Drupal.behaviors.initParagraphsEEDialogFilter = {
    attach(context) {
      $('.paragraphs-add-dialog--categorized', context).each(function () {
        const $dialog = $(this);
        if ($('.paragraphs-button--add-more', $dialog).length < 3) {
          // We do not need to enable the filter for very few items.
          return;
        }

        const $filterWrapper = $('.filter', $dialog);
        $filterWrapper.removeClass('js-hide');

        $filterWrapper.each(function (delta, elem) {
          $(once('paragraphs-ee-dialog-item-filter', '.item-filter', elem)).on(
            'input',
            function () {
              const $self = $(this);
              const $dialogWrapper = $self.closest('.ui-dialog-content');
              filterItems($dialogWrapper, this.value);
            },
          );
        });
      });
    },
  };

  /**
   * Init filter for categories in paragraphs modal.
   */
  Drupal.behaviors.initParagraphsEEDialogCategoriesFilter = {
    attach(context) {
      $('.paragraphs-add-dialog--categorized', context).each(function () {
        // Add handler for "All categories".
        const $tabCategoriesAll = $(
          '.paragraphs-ee-category-list-item__all',
          $(this),
        );
        $tabCategoriesAll.on('click', function () {
          const $dialog = $(this).closest('.paragraphs-ee-add-dialog');
          // Display all button groups.
          $('.paragraphs-ee-buttons .button-group', $dialog).removeClass(
            'is-hidden',
          );

          // Remove highlighting from previously selected category.
          $('.paragraphs-ee-category-list-item', $dialog).removeClass(
            'is-selected',
          );
          // Mark current item as selected.
          $(this).addClass('is-selected');
        });

        // Add handler for paragraph categories.
        const $tabCategories = $(
          '.paragraphs-ee-category-list-item:not(.paragraphs-ee-category-list-item__all)',
          $(this),
        );
        $tabCategories.on('click', function () {
          const $dialog = $(this).closest('.paragraphs-ee-add-dialog');
          // Hide all button groups.
          $('.paragraphs-ee-buttons .button-group', $dialog).addClass(
            'is-hidden',
          );
          // Display selected button group.
          const buttonGroupId = $('a', $(this)).attr('href');
          $(`.paragraphs-ee-buttons ${buttonGroupId}`, $dialog).removeClass(
            'is-hidden',
          );

          // Remove highlighting from previously selected category.
          $('.paragraphs-ee-category-list-item', $dialog).removeClass(
            'is-selected',
          );
          // Mark current item as selected.
          $(this).addClass('is-selected');

          return false;
        });
      });
    },
  };
})(jQuery, Drupal, drupalSettings, once);
