/**
 * Fade out and remove .js-auto-dismiss-flash alerts after a few seconds.
 */
(function () {
  'use strict';

  var DELAY_MS = 4000;
  var FADE_MS = 350;

  function dismiss(el) {
    el.style.transition = 'opacity ' + FADE_MS / 1000 + 's ease';
    el.style.opacity = '0';
    setTimeout(function () {
      if (el.parentNode) {
        el.parentNode.removeChild(el);
      }
    }, FADE_MS);
  }

  function run() {
    document.querySelectorAll('.js-auto-dismiss-flash').forEach(function (el) {
      setTimeout(function () {
        dismiss(el);
      }, DELAY_MS);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
