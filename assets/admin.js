(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-nexo-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (event) { if (!window.confirm(form.dataset.nexoConfirm)) event.preventDefault(); });
    });
    var add = document.getElementById('nexo-add-question');
    var questions = document.getElementById('nexo-questions');
    var counter = questions ? questions.querySelectorAll('.nexo-question').length : 0;
    if (add && questions) {
      add.addEventListener('click', function () {
        var template = document.getElementById('nexo-question-template');
        var wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(++counter));
        var fieldset = wrapper.firstElementChild;
        questions.appendChild(fieldset); fieldset.querySelector('input').focus();
      });
      questions.addEventListener('click', function (event) { if (event.target.classList.contains('nexo-remove-question')) event.target.closest('.nexo-question').remove(); });
    }
    var course = document.querySelector('[name="nexo_course_id"]');
    var topic = document.querySelector('[name="nexo_topic_id"]');
    function filterTopics() {
      if (!course || !topic) return;
      Array.from(topic.options).forEach(function (option) {
        if (!option.value || option.value === '0') return;
        option.hidden = option.dataset.course !== course.value; option.disabled = option.hidden;
        if (option.hidden && option.selected) topic.value = '0';
      });
    }
    if (course) { course.addEventListener('change', filterTopics); filterTopics(); }
    var selectLogo = document.getElementById('nexo-select-logo'); var removeLogo = document.getElementById('nexo-remove-logo');
    if (selectLogo && window.wp && wp.media) {
      var frame;
      selectLogo.addEventListener('click', function () {
        if (!frame) {
          frame = wp.media({title: 'Selecionar logo', button: {text: 'Usar este logo'}, multiple: false, library: {type: 'image'}});
          frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON(); document.getElementById('nexo-logo-id').value = attachment.id;
            var image = document.createElement('img'); image.src = attachment.url; image.alt = 'Logo selecionado'; document.getElementById('nexo-logo-preview').replaceChildren(image);
          });
        }
        frame.open();
      });
    }
    if (removeLogo) removeLogo.addEventListener('click', function () { document.getElementById('nexo-logo-id').value = '0'; document.getElementById('nexo-logo-preview').textContent = 'Nenhum logo selecionado'; });
  });
}());
