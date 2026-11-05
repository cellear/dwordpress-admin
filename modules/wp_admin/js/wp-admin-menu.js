/**
 * @file
 * Marks the current wp-admin menu item and shows its children inline.
 *
 * Core's Navigation module compares the full page URL with each link, which
 * fails when links carry a language prefix (/en/wp-admin vs /wp-admin). This
 * compares Drupal's internal path instead, then turns the current item's
 * flyout into an in-flow block, as WordPress lists Home / Updates under
 * Dashboard. Runs before Drupal behaviors attach, so core never binds its
 * popover handlers to that one item.
 */
(function (drupalSettings) {
  'use strict';

  var path = drupalSettings.path || {};
  var current = '/' + (path.currentPath || '').replace(/^\/+/, '');
  var prefix = '/' + (path.pathPrefix || '').replace(/\/+$/, '');
  var sidebar = document.querySelector('.admin-toolbar');
  if (!sidebar || !path.currentPath) {
    return;
  }

  function linkPath(a) {
    var p = a.pathname.replace(/\/+$/, '') || '/';
    if (prefix !== '/' && p.indexOf(prefix + '/') === 0) {
      p = p.substring(prefix.length);
    }
    return p === prefix ? '/' : p;
  }

  sidebar.querySelectorAll('a.toolbar-button').forEach(function (a) {
    if (linkPath(a) !== current) {
      return;
    }
    a.classList.add('current', 'is-active');
    var item = a.closest('[data-toolbar-popover]');
    if (!item || item.classList.contains('wp-admin-menu__item--current')) {
      return;
    }
    item.classList.add('wp-admin-menu__item--current');
    item.removeAttribute('data-toolbar-popover');
    var button = item.querySelector('[data-toolbar-popover-control]');
    if (button) {
      button.removeAttribute('data-toolbar-popover-control');
      button.removeAttribute('data-has-safe-triangle');
      button.setAttribute('aria-expanded', 'true');
    }
    var wrapper = item.querySelector('[data-toolbar-popover-wrapper]');
    if (wrapper) {
      wrapper.removeAttribute('inert');
    }
  });
})(window.drupalSettings || {});
