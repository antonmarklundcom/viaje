/* Admin editor UX: SEO counters, Google-snippet preview, markdown toolbar, preview pane. */
(function () {
  'use strict';

  function counters() {
    document.querySelectorAll('[data-counter]').forEach(function (el) {
      var max = parseInt(el.getAttribute('data-counter'), 10) || 60;
      var out = document.querySelector('.adm-counter[data-for="' + el.id + '"]');
      if (!out) { return; }
      var update = function () {
        var n = el.value.length;
        out.textContent = n + ' / ' + max;
        out.classList.toggle('is-over', n > max);
      };
      el.addEventListener('input', update);
      update();
    });
  }

  function snippet() {
    var title = document.getElementById('f-title');
    var seo = document.getElementById('f-seo_title');
    var desc = document.getElementById('f-description');
    var slug = document.getElementById('f-slug');
    var path = document.getElementById('f-path');
    var sT = document.getElementById('snippet-title');
    var sD = document.getElementById('snippet-desc');
    var sP = document.getElementById('snippet-path');
    if (!sT) { return; }
    var update = function () {
      sT.textContent = (seo && seo.value) || (title && title.value) || '';
      sD.textContent = (desc && desc.value) || '';
      if (sP) {
        var p = (path && path.value) || (slug && slug.value ? '/' + slug.value + '/' : sP.textContent);
        sP.textContent = p;
      }
    };
    [title, seo, desc, slug, path].forEach(function (el) { if (el) { el.addEventListener('input', update); } });
    update();
  }

  /* Slug auto-fills from the title only while it is still empty. */
  function slugify() {
    var title = document.getElementById('f-title');
    var slug = document.getElementById('f-slug');
    if (!title || !slug || slug.value) { return; }
    title.addEventListener('input', function () {
      slug.value = title.value.toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
      slug.dispatchEvent(new Event('input'));
    });
  }

  function toolbar() {
    var bar = document.querySelector('.adm-toolbar');
    if (!bar) { return; }
    var ta = document.getElementById(bar.getAttribute('data-target'));
    if (!ta) { return; }
    var wrap = function (before, after, placeholder) {
      var s = ta.selectionStart, e = ta.selectionEnd;
      var sel = ta.value.slice(s, e) || placeholder || '';
      ta.value = ta.value.slice(0, s) + before + sel + after + ta.value.slice(e);
      ta.focus();
      ta.selectionStart = s + before.length;
      ta.selectionEnd = s + before.length + sel.length;
    };
    bar.addEventListener('click', function (ev) {
      var kind = ev.target.getAttribute('data-md');
      if (!kind) { return; }
      if (kind === 'bold') { wrap('**', '**', 'texto'); }
      if (kind === 'h2') { wrap('\n## ', '\n', 'Subtítulo'); }
      if (kind === 'h3') { wrap('\n### ', '\n', 'Subtítulo'); }
      if (kind === 'link') { wrap('[', '](https://)', 'texto'); }
      if (kind === 'image') { wrap('![', '](/media/)', 'texto alternativo'); }
      if (kind === 'tip') { wrap('\n:::tip ', '\n\n:::\n', 'Consejo'); }
    });
  }

  function preview() {
    var btn = document.getElementById('md-preview-btn');
    var out = document.getElementById('md-preview');
    var ta = document.getElementById('f-body');
    var csrf = document.querySelector('input[name=csrf]');
    if (!btn || !out || !ta) { return; }
    btn.addEventListener('click', function () {
      var body = new URLSearchParams();
      body.set('body', ta.value);
      body.set('csrf', csrf ? csrf.value : '');
      fetch('/admin/preview-md', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (html) { out.innerHTML = html; out.hidden = false; })
        .catch(function () { out.textContent = 'Preview failed.'; out.hidden = false; });
    });
  }

  function copyFields() {
    document.querySelectorAll('.adm-copy').forEach(function (el) {
      el.addEventListener('focus', function () { el.select(); });
    });
  }

  counters(); snippet(); slugify(); toolbar(); preview(); copyFields();
})();
