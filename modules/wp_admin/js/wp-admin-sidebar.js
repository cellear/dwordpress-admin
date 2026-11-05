/**
 * @file
 * Keeps the Navigation sidebar expanded on desktop, like WordPress's menu.
 *
 * Core remembers a per-browser expanded/collapsed choice in localStorage
 * ("Drupal.navigation.sidebarExpanded") and mirrors it as the
 * data-admin-toolbar attribute on <html>. This runs in the <head>, before
 * core's inline script, so the first paint is already expanded; it then
 * undoes any later collapse at desktop width. Mobile is left to core.
 */
(function () {
  'use strict';

  var desktop = window.matchMedia('(min-width: 1024px)');
  var KEY = 'Drupal.navigation.sidebarExpanded';

  function expand() {
    if (!desktop.matches) {
      return;
    }
    try {
      window.localStorage.setItem(KEY, 'true');
    }
    catch (e) {
      // Private mode or storage disabled: the attribute alone is enough.
    }
    document.documentElement.setAttribute('data-admin-toolbar', 'expanded');
  }

  expand();
  desktop.addEventListener('change', expand);

  // Core (or anything else) asked for a collapse: re-expand on the next frame.
  document.addEventListener('toggle-admin-toolbar', function (event) {
    if (desktop.matches && event.detail && event.detail.state === false) {
      window.requestAnimationFrame(function () {
        document.documentElement.dispatchEvent(new CustomEvent('toggle-admin-toolbar', {
          bubbles: true,
          detail: { state: true, manual: false },
        }));
      });
    }
  });
})();
