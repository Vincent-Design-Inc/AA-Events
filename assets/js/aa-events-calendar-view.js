(function (factory) {
  'use strict';

  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
    return;
  }

  var controller = factory();

  function boot() {
    var storage;
    var mediaQuery;

    try {
      storage = window.localStorage;
    } catch (error) {
      storage = null;
    }

    if (typeof window.matchMedia === 'function') {
      mediaQuery = window.matchMedia('(max-width: 640px)');
    }

    controller.init(document, storage, mediaQuery);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(function () {
  'use strict';

  var STORAGE_KEY = 'aa-events-calendar-view';
  var CALENDAR_VIEW = 'calendar';
  var LIST_VIEW = 'list';

  function isValidView(view) {
    return view === CALENDAR_VIEW || view === LIST_VIEW;
  }

  function readPreference(storage) {
    var preference;

    try {
      preference = storage && storage.getItem(STORAGE_KEY);
    } catch (error) {
      preference = null;
    }

    return isValidView(preference) ? preference : CALENDAR_VIEW;
  }

  function writePreference(storage, preference) {
    try {
      if (storage) {
        storage.setItem(STORAGE_KEY, preference);
      }
    } catch (error) {
      // Storage can be unavailable even when the localStorage object exists.
    }
  }

  function initOccurrenceGroups(root) {
    if (root.__aaEventsOccurrenceGroupsInitialized) {
      return;
    }

    root.__aaEventsOccurrenceGroupsInitialized = true;

    var segments = root.querySelectorAll('[data-aa-events-occurrence]');
    var groups = Object.create(null);
    var segmentIndex;

    for (segmentIndex = 0; segmentIndex < segments.length; segmentIndex += 1) {
      var segment = segments[segmentIndex];
      var occurrenceKey = segment.getAttribute('data-aa-events-occurrence');
      var group;

      if (
        !occurrenceKey ||
        segment.closest('[data-aa-events-calendar]') !== root
      ) {
        continue;
      }

      group = groups[occurrenceKey];
      if (!group) {
        group = groups[occurrenceKey] = {
          segments: [],
          hoverCount: 0,
          focusCount: 0,
        };
      }

      group.segments.push(segment);
    }

    for (var occurrence in groups) {
      if (Object.prototype.hasOwnProperty.call(groups, occurrence)) {
        (function (group) {
          function update() {
            var active = group.hoverCount > 0 || group.focusCount > 0;

            for (var index = 0; index < group.segments.length; index += 1) {
              group.segments[index].classList.toggle('is-occurrence-active', active);
            }
          }

          for (var index = 0; index < group.segments.length; index += 1) {
            (function (segment) {
              var hovered = false;
              var focused = false;

              segment.addEventListener('mouseenter', function () {
                if (!hovered) {
                  hovered = true;
                  group.hoverCount += 1;
                  update();
                }
              });
              segment.addEventListener('mouseleave', function () {
                if (hovered) {
                  hovered = false;
                  group.hoverCount -= 1;
                  update();
                }
              });
              segment.addEventListener('focus', function () {
                if (!focused) {
                  focused = true;
                  group.focusCount += 1;
                  update();
                }
              });
              segment.addEventListener('blur', function () {
                if (focused) {
                  focused = false;
                  group.focusCount -= 1;
                  update();
                }
              });
            })(group.segments[index]);
          }
        })(groups[occurrence]);
      }
    }
  }

  function getInstance(root) {
    var switcher = root.querySelector('[data-aa-events-view-switcher]');
    var toggleButton = root.querySelector('[data-aa-events-view-toggle]');
    var calendarButton = root.querySelector(
      '[data-aa-events-view-button="calendar"]'
    );
    var listButton = root.querySelector('[data-aa-events-view-button="list"]');
    var calendarPanel = root.querySelector(
      '[data-aa-events-view-panel="calendar"]'
    );
    var listPanel = root.querySelector('[data-aa-events-view-panel="list"]');

    if (!calendarButton || !listButton || !calendarPanel || !listPanel) {
      return null;
    }

    return {
      root: root,
      switcher: switcher,
      toggleButton: toggleButton,
      calendarButton: calendarButton,
      listButton: listButton,
      calendarPanel: calendarPanel,
      listPanel: listPanel,
    };
  }

  function init(documentObject, storage, mediaQuery) {
    var roots = documentObject.querySelectorAll('[data-aa-events-calendar]');
    var instances = [];
    var preference;

    for (var index = 0; index < roots.length; index += 1) {
      initOccurrenceGroups(roots[index]);
      var instance = getInstance(roots[index]);

      if (instance) {
        instances.push(instance);
      }
    }

    if (instances.length === 0) {
      return;
    }

    preference = readPreference(storage);

    function applyView() {
      var mobile = Boolean(mediaQuery && mediaQuery.matches);

      for (var instanceIndex = 0; instanceIndex < instances.length; instanceIndex += 1) {
        var calendar = instances[instanceIndex];
        var showList = mobile || preference === LIST_VIEW;

        calendar.root.classList.toggle(
          'is-list-view',
          !mobile && preference === LIST_VIEW
        );
        calendar.root.classList.toggle(
          'is-calendar-view',
          preference === CALENDAR_VIEW
        );
        if (calendar.switcher) {
          calendar.switcher.classList.toggle(
            'is-list-view-active',
            !mobile && preference === LIST_VIEW
          );
        }
        calendar.calendarPanel.hidden = showList;
        calendar.listPanel.hidden = !showList;
        calendar.calendarButton.setAttribute(
          'aria-pressed',
          String(preference === CALENDAR_VIEW)
        );
        calendar.listButton.setAttribute(
          'aria-pressed',
          String(preference === LIST_VIEW)
        );
        if (calendar.toggleButton) {
          calendar.toggleButton.setAttribute(
            'aria-pressed',
            String(preference === LIST_VIEW)
          );
        }
      }
    }

    function selectView(view) {
      if (!isValidView(view)) {
        return;
      }

      preference = view;
      writePreference(storage, preference);
      applyView();
    }

    for (var buttonIndex = 0; buttonIndex < instances.length; buttonIndex += 1) {
      (function (calendar) {
        calendar.calendarButton.addEventListener('click', function () {
          selectView(CALENDAR_VIEW);
        });
        calendar.listButton.addEventListener('click', function () {
          selectView(LIST_VIEW);
        });
        if (calendar.toggleButton) {
          calendar.toggleButton.addEventListener('click', function () {
            selectView(preference === LIST_VIEW ? CALENDAR_VIEW : LIST_VIEW);
          });
        }
      })(instances[buttonIndex]);
    }

    if (mediaQuery) {
      if (typeof mediaQuery.addEventListener === 'function') {
        mediaQuery.addEventListener('change', applyView);
      } else if (typeof mediaQuery.addListener === 'function') {
        mediaQuery.addListener(applyView);
      }
    }

    applyView();
  }

  return { init: init };
});
