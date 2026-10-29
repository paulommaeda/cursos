/* Native forms handle mutations; JavaScript improves navigation and quiz editing. */
(function () {
  'use strict';
  function start() {
    const nav = document.querySelector('.np-navigation');
    if (nav) {
      const wide = matchMedia('(min-width:1000px)');
      const adjust = function () { nav.open = wide.matches; };
      adjust(); if (wide.addEventListener) wide.addEventListener('change', adjust);
    }
    document.querySelectorAll('.nexo-mobile-details').forEach(function (details) {
      const wide = matchMedia('(min-width:760px)');
      const adjust = function () { details.open = wide.matches; }; adjust();
      if (wide.addEventListener) wide.addEventListener('change', adjust);
    });
    if (location.hash === '#nexo-attendance-sheet') {
      requestAnimationFrame(function () { const sheet = document.getElementById('nexo-attendance-sheet'); if (sheet) sheet.scrollIntoView({block:'start',behavior:'instant'}); });
    }
    const questions = document.getElementById('nexo-questions');
    const add = document.getElementById('nexo-add-question');
    const template = document.getElementById('nexo-question-template');
    let next = 0;
    if (questions) questions.querySelectorAll('input[name]').forEach(function (input) {
      const match = input.name.match(/^nexo_questions\[(\d+)\]/); if (match) next = Math.max(next, Number(match[1]) + 1);
    });
    if (questions && add && template) {
      add.addEventListener('click', function () {
        const wrapper = document.createElement('div'); wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(next++));
        const fieldset = wrapper.firstElementChild; questions.appendChild(fieldset); fieldset.querySelector('input').focus();
      });
      questions.addEventListener('click', function (event) {
        if (event.target.classList.contains('nexo-remove-question')) event.target.closest('.nexo-question').remove();
      });
    }
    document.querySelectorAll('.nexo-portal .np-form').forEach(function (form) {
      form.addEventListener('submit', function () {
        if (!form.checkValidity()) return;
        const button = form.querySelector('button[type="submit"]');
        if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); button.textContent = 'Processando…'; }
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
}());
