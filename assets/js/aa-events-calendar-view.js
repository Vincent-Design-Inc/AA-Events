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
    initKeyboardNavigation(instances);
  }

  // -------------------------------------------------------------------------
  // Keyboard navigation for the calendar grid (arrow keys, Home/End, PgUp/Dn)
  // -------------------------------------------------------------------------
  //
  // After converting the grid from ARIA grid roles to a real <table>, browsers
  // no longer provide built-in arrow-key navigation between cells. The notes
  // explicitly call this out. This module re-implements roving-tabindex
  // keyboard nav so that arrow keys move focus between focusables (event
  // links and "More events" links) by day.
  //
  // Behavior:
  //   - Tab / Shift+Tab walk through every focusable in the grid sequentially
  //     (default browser behavior — no roving on Tab).
  //   - Arrow keys move focus day-by-day (Left/Right) or week-by-week (Up/Down),
  //     skipping cells with no interactive content.
  //   - Home / End jump to the start / end of the current week.
  //   - PageUp / PageDown jump to the same day-of-week one month back / forward
  //     by navigating to the calendar URL with new month/year params.
  //   - Left from week-1-day-1 wraps to the last focusable in the grid;
  //     Right from the last wraps to the first.
  //
  // Implementation uses roving tabindex: every focusable in the grid has
  // tabindex="-1" except the currently-active one which has tabindex="0".
  // Arrow keys update tabindex then call .focus() on the new active element.

  function initKeyboardNavigation(instances) {
    for (var index = 0; index < instances.length; index += 1) {
      var instance = instances[index];
      var grid = instance.calendarPanel.querySelector('table.aa-events-calendar-grid');
      if (!grid) {
        continue;
      }
      var tbody = grid.querySelector('tbody');
      if (!tbody) {
        continue;
      }
      initGridKeynav(grid, tbody);
    }
  }

  function initGridKeynav(grid, tbody) {
    var SELECTOR = '.aa-events-calendar-event, .aa-events-calendar-day-more-link';
    var focusables = Array.prototype.slice.call(tbody.querySelectorAll(SELECTOR));
    if (focusables.length === 0) {
      return;
    }

    // Build a (rowIndex, colIndex) -> [focusable,...] index. Each focusable
    // knows its cell by walking up to its <td>, and its row by the <td>'s
    // position among siblings within the <tr>.
    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr.aa-events-calendar-week'));
    var cellsByRow = rows.map(function (row) {
      return Array.prototype.slice.call(row.querySelectorAll('td.aa-events-calendar-day'));
    });
    var firstByCoord = Object.create(null); // "r,c" -> first focusable in cell
    var focusablesByCoord = Object.create(null); // "r,c" -> array of focusables

    focusables.forEach(function (el) {
      var cell = el.closest('td.aa-events-calendar-day');
      if (!cell) {
        return;
      }
      var rowEl = cell.parentElement;
      var r = rows.indexOf(rowEl);
      var c = cellsByRow[r] ? cellsByRow[r].indexOf(cell) : -1;
      if (r < 0 || c < 0) {
        return;
      }
      var key = r + ',' + c;
      if (!focusablesByCoord[key]) {
        focusablesByCoord[key] = [];
        firstByCoord[key] = el;
      }
      focusablesByCoord[key].push(el);
      el.dataset.aaEventsR = String(r);
      el.dataset.aaEventsC = String(c);
      el.setAttribute('tabindex', '-1');
    });

    // Pick the initial active focusable: prefer today-cell, else first.
    var todayCell = tbody.querySelector('td.today-cell');
    var active = null;
    if (todayCell) {
      var todayR = rows.indexOf(todayCell.parentElement);
      var todayC = cellsByRow[todayR] ? cellsByRow[todayR].indexOf(todayCell) : -1;
      if (todayR >= 0 && todayC >= 0) {
        active = firstByCoord[todayR + ',' + todayC] || null;
      }
    }
    if (!active) {
      active = focusables[0];
    }
    if (active) {
      active.setAttribute('tabindex', '0');
    }

    function setActive(next) {
      if (!next || next === active) {
        return;
      }
      if (active) {
        active.setAttribute('tabindex', '-1');
      }
      next.setAttribute('tabindex', '0');
      next.focus({ preventScroll: true });
      scrollWindowToCenter(next);
      active = next;
    }

    function scrollWindowToCenter(el) {
      // Explicitly scroll the WINDOW so the focused element lands near the
      // viewport center. We don't use Element.scrollIntoView() because the
      // calendar sits inside <main class="overflow-hidden"> and an
      // .aa-events-calendar-grid (also overflow: hidden) which intercept
      // scrollIntoView's ancestor walk — in Firefox, the browser resolves
      // to one of those hidden-overflow ancestors and silently fails to
      // scroll the page. window.scrollTo() bypasses that entirely.
      if (!el || typeof window === 'undefined') {
        return;
      }
      var rect = el.getBoundingClientRect();
      var viewportH = window.innerHeight || document.documentElement.clientHeight;
      // rect.top is the element's offset from the viewport top. To center it,
      // we want the new scrollY to be (currentScroll + rect.top) - viewportH/2.
      var docTop = (window.scrollY || window.pageYOffset || 0) + rect.top;
      var targetY = Math.round(docTop - viewportH / 2);
      var maxY = Math.max(
        0,
        (document.documentElement.scrollHeight || 0) - viewportH
      );
      targetY = Math.max(0, Math.min(targetY, maxY));
      window.scrollTo(0, targetY);
    }

    function findInRow(rowIndex, colIndex, direction) {
      // direction: -1 = scan left, +1 = scan right.
      // Walk all focusables in calendar reading order for the requested
      // direction:
      //   Left  = right-to-left, top-to-bottom (prev in row, then prev row's last)
      //   Right = left-to-right, top-to-bottom (next in row, then next row's first)
      // Skips the currently-active cell so the next focusable is found.
      var totalRows = cellsByRow.length;
      if (totalRows === 0) {
        return null;
      }

      // Build the linear ordering for the requested direction.
      var ordered = [];
      if (direction === 1) {
        for (var r = 0; r < totalRows; r += 1) {
          var rowR = cellsByRow[r];
          if (!rowR) continue;
          for (var c = 0; c < rowR.length; c += 1) {
            if (firstByCoord[r + ',' + c]) {
              ordered.push(firstByCoord[r + ',' + c]);
            }
          }
        }
      } else {
        for (var r2 = totalRows - 1; r2 >= 0; r2 -= 1) {
          var rowR2 = cellsByRow[r2];
          if (!rowR2) continue;
          for (var c2 = rowR2.length - 1; c2 >= 0; c2 -= 1) {
            if (firstByCoord[r2 + ',' + c2]) {
              ordered.push(firstByCoord[r2 + ',' + c2]);
            }
          }
        }
      }

      // Find the index of the active focusable in this ordering.
      var activeIdx = -1;
      for (var oi = 0; oi < ordered.length; oi += 1) {
        if (ordered[oi] === active) {
          activeIdx = oi;
          break;
        }
      }
      if (activeIdx < 0) {
        return ordered.length > 0 ? ordered[0] : null;
      }
      // Left (direction=-1): ordered is reversed; "previous in calendar
      //   order" = higher index in this reversed array.
      // Right (direction=+1): ordered is forward; "next in calendar
      //   order" = higher index.
      // In both cases, the next step is +1 index.
      var nextIdx = activeIdx + 1;
      if (nextIdx < 0 || nextIdx >= ordered.length) {
        return null; // boundary; caller will wrap
      }
      return ordered[nextIdx];
    }

    function findInCol(colIndex, rowIndex, direction) {
      // direction: -1 = scan up, +1 = scan down.
      // Walk all focusables column-by-column:
      //   Down = top-to-bottom in the same column, then next column to the right
      //   Up   = bottom-to-top in the same column, then prev column to the left
      // Prefer the same column over adjacent columns; only fall through to
      // adjacent columns if the current column has no focusables.
      function orderForColumn(col) {
        var out = [];
        for (var r = 0; r < cellsByRow.length; r += 1) {
          if (firstByCoord[r + ',' + col]) {
            out.push(firstByCoord[r + ',' + col]);
          }
        }
        return out;
      }

      // Try the requested column first.
      var sameCol = orderForColumn(colIndex);
      if (sameCol.length > 0) {
        var activeIdx = sameCol.indexOf(active);
        if (activeIdx >= 0) {
          var nextIdx = activeIdx + direction;
          // Wrap within column: from the top going Up, jump to bottom-most
          // entry in same column; from the bottom going Down, jump to top.
          if (nextIdx < 0) {
            return sameCol[sameCol.length - 1];
          }
          if (nextIdx >= sameCol.length) {
            return sameCol[0];
          }
          return sameCol[nextIdx];
        } else {
          // Active isn't in this column (e.g. it was deleted by a re-render).
          // Pick the entry closest in row distance.
          var best = null;
          var bestDist = Infinity;
          for (var i = 0; i < sameCol.length; i += 1) {
            var r = parseInt(sameCol[i].dataset.aaEventsR, 10);
            var dist = Math.abs(r - rowIndex);
            if (dist < bestDist) {
              bestDist = dist;
              best = sameCol[i];
            }
          }
          return best;
        }
      }

      // Column is empty: scan adjacent columns in the requested direction
      // (then the opposite direction as a fallback).
      function scanAdjacent(startCol, step) {
        var c = startCol + step;
        while (c >= 0 && c < (cellsByRow[0] ? cellsByRow[0].length : 7)) {
          var col = orderForColumn(c);
          if (col.length > 0) {
            var bestEntry = null;
            var bestDist = Infinity;
            for (var i2 = 0; i2 < col.length; i2 += 1) {
              var el = col[i2];
              var r2 = parseInt(el.dataset.aaEventsR, 10);
              var dist2 = Math.abs(r2 - rowIndex);
              if (dist2 < bestDist) {
                bestDist = dist2;
                bestEntry = el;
              }
            }
            return bestEntry;
          }
          c += step;
        }
        return null;
      }

      return scanAdjacent(colIndex, direction) || scanAdjacent(colIndex, -direction);
    }

    function findInWeek(rowIndex, endCol) {
      var row = cellsByRow[rowIndex];
      if (!row) {
        return null;
      }
      var start = endCol === 'first' ? 0 : row.length - 1;
      var step = endCol === 'first' ? 1 : -1;
      var c = start;
      while (c >= 0 && c < row.length) {
        var k = rowIndex + ',' + c;
        if (firstByCoord[k]) {
          return firstByCoord[k];
        }
        c += step;
      }
      return null;
    }

    function findWrap(direction) {
      // direction: -1 = previous boundary (last focusable), +1 = next boundary (first focusable)
      // Only used as a fallback when the arrow direction has no further cell
      // in the same row/column. The semantics: Left wraps to "the last thing",
      // Up wraps to "the last thing", Right/Down wrap to "the first thing".
      var ordered = direction === -1
        ? focusables.slice().reverse()
        : focusables.slice();
      for (var i = 0; i < ordered.length; i += 1) {
        if (ordered[i] !== active) {
          return ordered[i];
        }
      }
      return null;
    }

    function jumpMonth(delta) {
      // delta: -1 = previous month, +1 = next month.
      // Find an active focusable, read its cell's aria-label date, compute
      // the same day-of-week one month forward/back, and navigate to the
      // calendar URL with new month/year params.
      if (!active) {
        return;
      }
      var cell = active.closest('td.aa-events-calendar-day');
      if (!cell) {
        return;
      }
      var label = cell.getAttribute('aria-label') || '';
      // aria-label format: "September 24, 2026" (en_US).
      var parsed = parseCalendarLabel(label);
      if (!parsed) {
        return;
      }
      var target = new Date(parsed.year, parsed.month - 1 + delta, parsed.day);
      var url = new URL(window.location.href);
      url.searchParams.set('aa_month', String(target.getMonth() + 1));
      url.searchParams.set('aa_year', String(target.getFullYear()));
      window.location.assign(url.toString());
    }

    grid.addEventListener('keydown', function (event) {
      if (!active) {
        return;
      }
      var key = event.key;
      var r = parseInt(active.dataset.aaEventsR, 10);
      var c = parseInt(active.dataset.aaEventsC, 10);
      var next = null;

      if (key === 'ArrowLeft') {
        event.preventDefault();
        next = findInRow(r, c, -1) || findWrap(-1);
      } else if (key === 'ArrowRight') {
        event.preventDefault();
        next = findInRow(r, c, 1) || findWrap(1);
      } else if (key === 'ArrowUp') {
        event.preventDefault();
        next = findInCol(c, r, -1) || findWrap(-1);
      } else if (key === 'ArrowDown') {
        event.preventDefault();
        next = findInCol(c, r, 1) || findWrap(1);
      } else if (key === 'Home') {
        event.preventDefault();
        next = findInWeek(r, 'first') || active;
      } else if (key === 'End') {
        event.preventDefault();
        next = findInWeek(r, 'last') || active;
      } else if (key === 'PageUp') {
        event.preventDefault();
        jumpMonth(-1);
        return;
      } else if (key === 'PageDown') {
        event.preventDefault();
        jumpMonth(1);
        return;
      } else {
        return;
      }

      if (next && next !== active) {
        setActive(next);
      }
    });
  }

  var CALENDAR_LABEL_MONTHS = {
    January: 1, February: 2, March: 3, April: 4, May: 5, June: 6,
    July: 7, August: 8, September: 9, October: 10, November: 11, December: 12,
  };

  function parseCalendarLabel(label) {
    // Matches "September 24, 2026" or localized equivalents (English-only for now).
    var match = /^([A-Za-z]+)\s+(\d{1,2}),\s+(\d{4})$/.exec(label.trim());
    if (!match) {
      return null;
    }
    var monthName = match[1];
    var day = parseInt(match[2], 10);
    var year = parseInt(match[3], 10);
    var month = CALENDAR_LABEL_MONTHS[monthName];
    if (!month || isNaN(day) || isNaN(year)) {
      return null;
    }
    return { year: year, month: month, day: day };
  }

  return { init: init };
});
