/* Progressive enhancement only. Learning, login and checkout work without this file. */
(function () {
  'use strict';
  document.querySelectorAll('.nexo-quiz').forEach(function (form) {
    form.addEventListener('submit', function () {
      if (!form.checkValidity()) return;
      const button = form.querySelector('button[type="submit"]');
      if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); button.textContent = 'Enviando respostas…'; }
    });
  });
  document.querySelectorAll('.nexo-curriculum-item.is-selected').forEach(function (item) { item.setAttribute('aria-current', 'step'); });
}());
(function () {
  function initializeNavigation() {
    document.querySelectorAll('.nexo-navigation-menu').forEach(function (menu) {
      var mobile = window.matchMedia('(max-width: 1000px)');
      function synchronize() { menu.open = !mobile.matches; }
      synchronize();
      if (mobile.addEventListener) { mobile.addEventListener('change', synchronize); }
      else { mobile.addListener(synchronize); }
    });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initializeNavigation); }
  else { initializeNavigation(); }
}());
