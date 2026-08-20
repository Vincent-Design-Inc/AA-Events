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
    var wrapper = document.createElement('span');
    wrapper.className = 'aa-event-occurrence-item';
    wrapper.innerHTML = markup;
    target.appendChild(wrapper);
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
          var target = button.parentNode;
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
