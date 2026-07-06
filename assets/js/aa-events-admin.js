(function($) {
  'use strict';

  // Guard against a jQuery UI datepicker crash in the block editor meta box area.
  // In some contexts, _findPos receives a node with no positioned parent and throws.
  if ($.datepicker && $.datepicker._findPos) {
    var originalFindPos = $.datepicker._findPos;
    $.datepicker._findPos = function(obj) {
      try {
        if (!obj || !obj.ownerDocument || !obj.ownerDocument.documentElement) {
          return [0, 0];
        }
        return originalFindPos.call(this, obj);
      } catch (e) {
        return [0, 0];
      }
    };
  }

  // After repeater rows are appended, remove accidental focus from date inputs
  // so datepicker doesn't try to open against a transient/hidden element.
  if (window.acf && window.acf.addAction) {
    window.acf.addAction('append', function($el) {
      var $dateInputs = $el.find('.acf-date-picker input.hasDatepicker');
      if ($dateInputs.length) {
        setTimeout(function() {
          $dateInputs.first().trigger('blur');
        }, 0);
      }
    });
  }

  function syncAllDayRows($scope) {
    var $repeaters = $scope.find('.acf-field[data-name="event_dates"]')
      .addBack('.acf-field[data-name="event_dates"]')
      .add($scope.closest('.acf-field[data-name="event_dates"]'));

    $repeaters.find('.acf-row').each(function() {
      var $row = $(this);
      var checked = $row.find('.acf-field[data-name="all_day"] input[type="checkbox"]').first().is(':checked');
      var $timeFields = $row.find('.acf-field[data-name="start_time"], .acf-field[data-name="end_time"]');
      $row.toggleClass('aa-events-is-all-day', checked);
      $timeFields.toggle(!checked).attr('aria-hidden', checked ? 'true' : 'false');
      $timeFields.find('input').prop('disabled', checked);
    });
  }

  $(document).on('change', '.acf-field[data-name="event_dates"] .acf-field[data-name="all_day"] input[type="checkbox"]', function() {
    var $checkbox = $(this);
    var $row = $checkbox.closest('.acf-row');
    if ($checkbox.is(':checked')) {
      $row.find('.acf-field[data-name="start_time"] input, .acf-field[data-name="end_time"] input').val('');
    }
    syncAllDayRows($row.closest('.acf-field[data-name="event_dates"]'));
  });

  $(function() {
    syncAllDayRows($(document));
  });

  if (window.acf && window.acf.addAction) {
    window.acf.addAction('ready', function($el) {
      syncAllDayRows($el || $(document));
    });
    window.acf.addAction('append', function($el) {
      syncAllDayRows($el);
    });
  }
})(jQuery);
