(function (factory) {
  'use strict';

  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
    return;
  }

  var controller = factory();

  function boot() {
    controller.init(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(function () {
  'use strict';

  function getConfig() {
    if (typeof window.aaEventsRecurrence !== 'object' || !window.aaEventsRecurrence) {
      return null;
    }
    return window.aaEventsRecurrence;
  }

  function appendItem(target, markup) {
    var wrapper = document.createElement('div');
    wrapper.className = 'aa-event-occurrence-item';
    wrapper.innerHTML = markup;
    target.appendChild(wrapper);
  }

  function resolveTarget(button) {
    var target_selector = button.getAttribute('data-target');
    if (target_selector) {
      var explicit = document.querySelector(target_selector);
      if (explicit) {
        return explicit;
      }
    }

    // Walk up from the clicked button to the show-more wrapper, then look
    // for the occurrence list as a previous sibling. This works even when
    // the button itself lives inside a custom element (e.g. <x-button>)
    // that does not forward data-* attributes into its shadow tree.
    var wrapper = button.closest('.aa-event-show-more-wrapper');
    if (wrapper && wrapper.previousElementSibling) {
      var sibling = wrapper.previousElementSibling;
      if (sibling.classList && sibling.classList.contains('aa-event-occurrence-list')) {
        return sibling;
      }
    }

    var ancestor = button.parentElement;
    while (ancestor && !ancestor.classList.contains('aa-event-occurrence-list')) {
      ancestor = ancestor.parentElement;
    }
    return ancestor || button.parentNode;
  }

  function bind(button) {
    if (button.__aaEventsBound) {
      return;
    }
    button.__aaEventsBound = true;

    var config = getConfig();
    if (!config || !config.restUrl || !config.restNonce) {
      button.parentNode && button.parentNode.removeChild(button);
      return;
    }

    var target = resolveTarget(button);
    if (!target) {
      button.parentNode && button.parentNode.removeChild(button);
      return;
    }

    button.addEventListener('click', function () {
      var offset = parseInt(button.getAttribute('data-offset') || '0', 10);
      var limit = parseInt(button.getAttribute('data-window') || '5', 10);
      var url = config.restUrl + '?offset=' + encodeURIComponent(offset) + '&limit=' + encodeURIComponent(limit);

      button.disabled = true;
      button.setAttribute('aria-busy', 'true');

      fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': config.restNonce },
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Request failed: ' + response.status);
          }
          return response.json();
        })
        .then(function (payload) {
          var items = payload && payload.items ? payload.items : [];
          var index;

          for (index = 0; index < items.length; index += 1) {
            appendItem(target, items[index].markup);
          }

          if (payload && payload.has_more) {
            button.setAttribute('data-offset', String(payload.next_offset));
            button.disabled = false;
            button.removeAttribute('aria-busy');
          } else {
            if (button.parentNode) {
              button.parentNode.removeChild(button);
            }
          }
        })
        .catch(function () {
          button.disabled = false;
          button.removeAttribute('aria-busy');
        });
    });
  }

  function init(documentObject) {
    var buttons = documentObject.querySelectorAll('.aa-event-show-more');
    var index;

    for (index = 0; index < buttons.length; index += 1) {
      bind(buttons[index]);
    }
  }

  return { init: init };
});
