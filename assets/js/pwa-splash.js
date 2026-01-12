(function () {
  "use strict";

  function isStandalone() {
    try {
      const iosStandalone = (window.navigator && window.navigator.standalone === true);
      const displayModeStandalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
      return iosStandalone || displayModeStandalone;
    } catch (e) { return false; }
  }

  const el = document.getElementById('gdy-splash');
  if (!el) return;

  // show only when installed / standalone
  if (!isStandalone()) {
    el.remove();
    return;
  }

  el.classList.add('is-visible');

  function hide() {
    el.classList.add('is-hiding');
    setTimeout(() => { try { el.remove(); } catch (e) {} }, 320);
  }

  window.addEventListener('load', () => {
    setTimeout(hide, 350);
  });

  // Safety timeout
  setTimeout(hide, 2200);
})();
